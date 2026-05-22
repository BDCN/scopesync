<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PromptLoader {

    protected $_cache      = [];
    protected $_promptsDir;

    public function __construct()
    {
        $this->_promptsDir = APPPATH . '../prompts/';
    }

    /**
     * Load a prompt file and return ['system', 'user', 'version'].
     * Results are cached in-memory per request.
     */
    public function load(string $name): array
    {
        if (isset($this->_cache[$name])) {
            return $this->_cache[$name];
        }

        $path = $this->_promptsDir . $name . '.md';
        if ( ! file_exists($path)) {
            throw new RuntimeException("Prompt file not found: {$path}");
        }

        $raw    = file_get_contents($path);
        $result = $this->_parse($raw, $name);

        $this->_cache[$name] = $result;
        return $result;
    }

    protected function _parse(string $content, string $filename): array
    {
        $version = $this->_extractVersion($content, $filename);

        // Strip YAML frontmatter (---\n...\n---\n)
        $body = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $content);

        $system = '';
        $user   = '';

        // Locate ## SYSTEM and ## USER using string positions (safe with nested ### and code blocks)
        $sysMarker = "\n## SYSTEM";
        $usrMarker = "\n## USER";

        $sysPos = strpos($body, $sysMarker);
        $usrPos = strpos($body, $usrMarker);

        if ($sysPos !== FALSE && $usrPos !== FALSE && $usrPos > $sysPos) {
            // System content: after "## SYSTEM\n" up to "\n## USER"
            $sysStart = strpos($body, "\n", $sysPos + 1) + 1;
            $system   = trim(substr($body, $sysStart, $usrPos - $sysStart));

            // User content: after "## USER\n" up to next "\n## " section (or end)
            $usrStart = strpos($body, "\n", $usrPos + 1) + 1;
            $nextSec  = strpos($body, "\n## ", $usrStart);
            $user     = $nextSec !== FALSE
                ? trim(substr($body, $usrStart, $nextSec - $usrStart))
                : trim(substr($body, $usrStart));

            // Drop trailing "---" separator lines from each section
            $system = rtrim(preg_replace('/\n---\s*$/', '', $system));
            $user   = rtrim(preg_replace('/\n---\s*$/', '', $user));
        }

        return ['system' => $system, 'user' => $user, 'version' => $version];
    }

    protected function _extractVersion(string $content, string $filename): string
    {
        // Prefer frontmatter: "version: v1"
        if (preg_match('/^---\s*\n(.*?)\n---/s', $content, $fm)) {
            if (preg_match('/^version:\s*(.+)$/m', $fm[1], $m)) {
                return trim($m[1]);
            }
        }
        // Fall back to filename suffix: name_v2 -> v2
        if (preg_match('/_v(\d+)$/', $filename, $m)) {
            return 'v' . $m[1];
        }
        return 'v1';
    }
}
