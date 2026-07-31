<?php
declare(strict_types=1);
/**
 * NuErrorLogger — captures PHP errors, uncaught exceptions, SQL errors, and JS errors.
 * PHP 7.4 compatible (no match expression, no str_contains, no str_starts_with).
 *
 * Register AFTER NuDatabase and NuAuth are loaded in index.php bootstrap.
 * Falls back to logs/nuerror.log if DB is not yet ready.
 */
class NuErrorLogger {

    private static $registered = false;
    private static $instance   = null;

    const SEV_DEBUG   = 'debug';
    const SEV_INFO    = 'info';
    const SEV_WARNING = 'warning';
    const SEV_ERROR   = 'error';
    const SEV_FATAL   = 'fatal';

    const TYPE_PHP = 'PHP';
    const TYPE_SQL = 'SQL';
    const TYPE_JS  = 'JS';
    const TYPE_APP = 'APP';

    private function __construct() {}

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register global PHP error / exception / shutdown handlers.
     * Safe to call even before DB is ready — write() falls back to file.
     */
    public static function register(): void {
        if (self::$registered) return;
        self::$registered = true;
        set_error_handler([self::getInstance(), 'handlePhpError']);
        set_exception_handler([self::getInstance(), 'handleException']);
        register_shutdown_function([self::getInstance(), 'handleShutdown']);
    }

    /** set_error_handler callback */
    public function handlePhpError(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool {
        $context = [
            'errno'   => $errno,
            'errtype' => $this->phpErrnoToName($errno),
            'file'    => $this->stripRoot($errfile),
            'line'    => $errline,
        ];
        $trace = $this->buildTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8));
        $this->write(self::TYPE_PHP, $this->phpErrnoToSeverity($errno), $errstr, $context, $trace, $errfile, $errline);
        return false;
    }

    /** set_exception_handler callback */
    public function handleException(\Throwable $e): void {
        $message = get_class($e) . ': ' . $e->getMessage();
        $context = [
            'exception' => get_class($e),
            'code'      => $e->getCode(),
            'file'      => $this->stripRoot($e->getFile()),
            'line'      => $e->getLine(),
        ];
        $trace = $this->buildTrace($e->getTrace());
        $this->write(self::TYPE_PHP, self::SEV_FATAL, $message, $context, $trace, $e->getFile(), $e->getLine());
    }

    /** register_shutdown_function — catches E_ERROR, E_PARSE, E_COMPILE_ERROR */
    public function handleShutdown(): void {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $this->write(
                self::TYPE_PHP,
                self::SEV_FATAL,
                $error['message'],
                [
                    'errno'   => $error['type'],
                    'errtype' => $this->phpErrnoToName($error['type']),
                    'file'    => $this->stripRoot($error['file']),
                    'line'    => $error['line'],
                ],
                null,
                $error['file'],
                $error['line']
            );
        }
    }

    /**
     * Log a SQL error in a catch block:
     *   NuErrorLogger::logSql($sql, $e->getMessage(), $params, __FILE__, __LINE__);
     */
    public static function logSql(
        string $sql,
        string $error,
        array $params = [],
        ?string $callerFile = null,
        int $callerLine = 0
    ): void {
        $safeParams = [];
        foreach ($params as $k => $v) {
            $key = strtolower((string)$k);
            if (strpos($key, 'pass') !== false || strpos($key, 'secret') !== false || strpos($key, 'token') !== false) {
                $safeParams[$k] = '***';
            } else {
                $safeParams[$k] = $v;
            }
        }
        $context = ['sql' => $sql, 'params' => $safeParams];
        if ($callerFile !== null) $context['file'] = self::getInstance()->stripRoot($callerFile);
        if ($callerLine)          $context['line'] = $callerLine;
        $trace = self::getInstance()->buildTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8));
        self::getInstance()->write(self::TYPE_SQL, self::SEV_ERROR, $error, $context, $trace, (string)$callerFile, $callerLine);
    }

    /**
     * Log a JS error payload — called from api/errorlog.php.
     */
    public static function logJs(array $payload): void {
        $message = isset($payload['message']) ? $payload['message'] : 'Unknown JS error';
        $context = [
            'source'    => isset($payload['source'])    ? $payload['source']    : '',
            'lineno'    => isset($payload['lineno'])     ? $payload['lineno']    : 0,
            'colno'     => isset($payload['colno'])      ? $payload['colno']     : 0,
            'url'       => isset($payload['url'])        ? $payload['url']       : '',
            'userAgent' => isset($payload['userAgent'])  ? $payload['userAgent'] : '',
        ];
        $trace = isset($payload['stack']) ? (string)$payload['stack'] : null;
        self::getInstance()->write(
            self::TYPE_JS,
            self::SEV_ERROR,
            $message,
            $context,
            $trace,
            (string)(isset($payload['source']) ? $payload['source'] : ''),
            (int)(isset($payload['lineno']) ? $payload['lineno'] : 0)
        );
    }

    /**
     * Log any app-level message from PHP:
     *   NuErrorLogger::logApp('Save failed', ['form_id' => 5], NuErrorLogger::SEV_WARNING);
     */
    public static function logApp(
        string $message,
        array $context = [],
        string $severity = self::SEV_INFO
    ): void {
        $trace = self::getInstance()->buildTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8));
        self::getInstance()->write(self::TYPE_APP, $severity, $message, $context, $trace);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal write — NEVER throws, NEVER crashes the app
    // ─────────────────────────────────────────────────────────────────────────

    private function write(
        string $type,
        string $severity,
        string $message,
        array $context = [],
        ?string $trace = null,
        string $file = '',
        int $line = 0
    ): void {
        $userId   = null;
        $userName = null;
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $userId   = isset($_SESSION['nu_user_id'])  ? $_SESSION['nu_user_id']  : null;
                $userName = isset($_SESSION['nu_username']) ? $_SESSION['nu_username'] : null;
            }
        } catch (\Throwable $ignored) {}

        $requestUri    = isset($_SERVER['REQUEST_URI'])    ? $_SERVER['REQUEST_URI']    : '';
        $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';

        // Try DB — only if NuDatabase class is already loaded
        if (class_exists('NuDatabase', false)) {
            try {
                $db = NuDatabase::getInstance();
                $db->query(
                    "INSERT INTO nu_error_log
                        (errlog_type, errlog_severity, errlog_message, errlog_context,
                         errlog_trace, errlog_file, errlog_line,
                         errlog_request_uri, errlog_request_method,
                         errlog_user_id, errlog_user_name, errlog_created_at)
                     VALUES
                        (:type, :sev, :msg, :ctx,
                         :trace, :file, :line,
                         :uri, :method,
                         :uid, :uname, NOW())",
                    [
                        ':type'   => $type,
                        ':sev'    => $severity,
                        ':msg'    => mb_substr($message, 0, 2000),
                        ':ctx'    => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ':trace'  => ($trace !== null) ? mb_substr($trace, 0, 8000) : null,
                        ':file'   => mb_substr($this->stripRoot($file), 0, 500),
                        ':line'   => $line,
                        ':uri'    => mb_substr($requestUri, 0, 500),
                        ':method' => $requestMethod,
                        ':uid'    => $userId,
                        ':uname'  => $userName,
                    ]
                );
                return;
            } catch (\Throwable $dbErr) {
                error_log('[NuErrorLogger] DB write failed: ' . $dbErr->getMessage());
                // fall through to file log
            }
        }

        // File fallback — always works, even before DB is ready
        $logDir  = defined('NU_ROOT') ? NU_ROOT . '/logs' : __DIR__ . '/../logs';
        $logFile = $logDir . '/nuerror.log';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $cleanFile = $this->stripRoot($file);
        $ctxJson   = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $entry = sprintf(
            "[%s] [%s] [%s] %s | %s:%d | %s | %s\n",
            date('Y-m-d H:i:s'),
            $type,
            strtoupper($severity),
            $message,
            $cleanFile,
            $line,
            $requestUri,
            $ctxJson
        );
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        error_log('[NuErrorLogger] [' . $type . '] ' . $severity . ': ' . $message . ' | ' . $cleanFile . ':' . $line);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers — all PHP 7.4 compatible, no match/str_contains/str_starts_with
    // ─────────────────────────────────────────────────────────────────────────

    private function buildTrace(array $frames): string {
        $lines = [];
        foreach ($frames as $i => $f) {
            $file  = $this->stripRoot(isset($f['file'])     ? $f['file']     : '');
            $fline = isset($f['line'])     ? $f['line']     : '?';
            $class = isset($f['class'])    ? $f['class']    : '';
            $ftype = isset($f['type'])     ? $f['type']     : '';
            $fn    = isset($f['function']) ? $f['function'] : '';
            $lines[] = '#' . $i . ' ' . $class . $ftype . $fn . '() - ' . $file . ':' . $fline;
        }
        return implode("\n", $lines);
    }

    private function stripRoot(string $path): string {
        $root = defined('NU_ROOT') ? NU_ROOT : '';
        if ($root !== '' && strpos($path, $root) === 0) {
            return ltrim(substr($path, strlen($root)), DIRECTORY_SEPARATOR);
        }
        return $path;
    }

    /** PHP 7.4 — if/elseif instead of match() */
    private function phpErrnoToSeverity(int $errno): string {
        if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            return self::SEV_FATAL;
        }
        if (in_array($errno, [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING], true)) {
            return self::SEV_WARNING;
        }
        if (in_array($errno, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED], true)) {
            return self::SEV_INFO;
        }
        return self::SEV_ERROR;
    }

    private function phpErrnoToName(int $errno): string {
        $map = [
            E_ERROR             => 'E_ERROR',
            E_WARNING           => 'E_WARNING',
            E_PARSE             => 'E_PARSE',
            E_NOTICE            => 'E_NOTICE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_CORE_WARNING      => 'E_CORE_WARNING',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_USER_WARNING      => 'E_USER_WARNING',
            E_USER_NOTICE       => 'E_USER_NOTICE',
            E_STRICT            => 'E_STRICT',
            E_DEPRECATED        => 'E_DEPRECATED',
            E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
            E_ALL               => 'E_ALL',
        ];
        return isset($map[$errno]) ? $map[$errno] : 'E_UNKNOWN(' . $errno . ')';
    }

    private function __clone() {}
    public function __wakeup() { throw new \RuntimeException('Cannot unserialize NuErrorLogger.'); }
}
