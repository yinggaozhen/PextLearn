<?php

namespace Yaf\View;

use const YAF\ERR\NOTFOUND\VIEW;
use const YAF\ERR\TYPE_ERROR;
use Yaf\View_Interface;

class Simple implements View_Interface
{
    protected $_tpl_vars;

    protected $_tpl_dir;

    protected $_options;

    /**
     * Simple constructor.
     * @param string $tpl_dir
     * @param array|null $options
     * @throws \Exception
     */
    final public function __construct(string $tpl_dir, array $options = null)
    {
        $this->_tpl_vars = [];

        if (!empty($tpl_dir)) {
            if (realpath($tpl_dir) == $tpl_dir) {
                $this->_tpl_dir = $tpl_dir;
            } else {
                throw new \Exception("Expects an absolute path for templates directory", TYPE_ERROR);
            }
        }

        if (!empty($options) && is_array($options)) {
            $this->_options = $options;
        }
    }

    /**
     * @param $name
     * @param null $value
     * @return $this|bool
     */
    function assign($name, $value = null)
    {
        $argc = func_get_args();

        if ($argc == 1) {
            if ($this->assignMulti($value)) {
                return $this;
            }
        } else if ($argc == 2) {
            if ($this->assignSingle($name, $value)) {
                return $this;
            }
        } else {
            // WRONG_PARAM_COUNT
        }

        return false;
    }

    /**
     * @param $tpl
     * @param null $tpl_vars
     * @throws \Exception
     */
    function display($tpl, $tpl_vars = null): void
    {
        $this->simpleRender($tpl, $tpl_vars, $null);
    }

    /**
     * @param $tpl
     * @param $tpl_vars
     * @throws \Exception
     */
    function render($tpl, $tpl_vars): void
    {
        $this->simpleRender($tpl, $tpl_vars, $return_value);
    }

    /**
     * @param string $template_dir
     * @return $this|bool
     */
    function setScriptPath(string $template_dir)
    {
        if (is_string($template_dir) && realpath($template_dir) == $template_dir) {
            $this->_tpl_dir = $template_dir;

            return $this;
        }

        return false;
    }

    /**
     * @return string
     */
    function getScriptPath(): string
    {
        $tpl_dir = $this->_tpl_dir;

        if ((empty($tpl_dir) || !is_string($tpl_dir)) && YAF_G('view_directory')) {
            return YAF_G('view_directory');
        }

        return $tpl_dir;
    }

    /**
     * @param string|null $name
     * @return array|mixed|null
     */
    public function get(?string $name = null)
    {
        if (!empty($this->_tpl_vars) && is_array($this->_tpl_vars)) {
            if (empty($name)) {
                return $this->_tpl_vars;
            }

            return $this->_tpl_vars[$name] ?? null;
        }

        return null;
    }

    /**
     * @param string $tpl_content
     * @param array|null $vars
     * @return bool
     * @throws \Exception
     */
    public function eval(string $tpl_content, array $vars = null): ?bool
    {
        // TODO 这里execTpl解读还没彻底理解
        if (!$this->simpleEval($tpl_content, $vars, $result_value)) {
            return false;
        }
    }

    /**
     * @param string $name
     * @param $value
     * @return $this
     */
    public function assignRef(string $name, $value): Simple
    {
        $this->_tpl_vars[$name] = $value;

        return $this;
    }

    public function clear(string $name): void
    {
        if (empty($name)) {
            $this->_tpl_vars = [];
        } else {
            unset($this->_tpl_vars[$name]);
        }
    }

    /**
     * @param $value
     * @return int
     */
    private function assignMulti($value): int
    {
        if (is_array($value)) {
            $this->_tpl_vars = $value;
            return 1;
        }

        return 0;
    }

    /**
     * @param string $name
     * @param $value
     * @return int
     */
    private function assignSingle(string $name, $value): int
    {
        try {
            $this->_tpl_vars[$name] = $value;
            return 1;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @param string $tpl
     * @param $vars
     * @param $result
     * @return int
     * @throws \Exception
     */
    private function simpleRender(string $tpl, $vars, &$result): int
    {
        $tpl_vars = $this->_tpl_vars[$tpl];

        $symbol_table = $this->buildSymtable($tpl_vars, $vars);

        // 判断是否为绝对路径
        if (realpath($tpl) == $tpl) {
            if ($this->renderTpl($symbol_table, $tpl, $result) == 0) {
                return 0;
            }
        } else {
            $tpl_dir = $this->_tpl_dir;

            if (!is_string($tpl_dir)) {
                if (YAF_G('view_directory')) {
                    $script = sprintf("%s%c%s", YAF_G('view_directory'), DIRECTORY_SEPARATOR, $tpl);
                } else {
                    $message = sprintf("Could not determine the view script path, you should call %s::setScriptPath to specific it", self::class);
                    throw new \Exception($message, VIEW);
                }
            } else {
                $script = sprintf("%s%c%s", $tpl_dir, DIRECTORY_SEPARATOR, $tpl);
            }

            if ($this->renderTpl($symbol_table, $script, $result) == 0) {
                return 0;
            }
        }

        return 1;
    }

    /**
     * @param array $symbol_table
     * @param string $_internal_tpl
     * @param null|string $_internal_result
     * @return int
     * @throws \Exception
     */
    private function renderTpl(array $symbol_table, string $_internal_tpl, ?string &$_internal_result): int
    {
        // TODO 是否需要extract
        extract($symbol_table);

        if (realpath($_internal_tpl) != $_internal_tpl) {
            throw new \Exception(sprintf("Failed opening template %s", $_internal_tpl), VIEW);
        }

        if (!is_readable($_internal_tpl)) {
            trigger_error('Unable to fetch ob content', E_WARNING);
            return 0;
        }

        ob_start();
        $_internal_result = include $_internal_tpl;

        if (is_null($_internal_result)) {
            ob_end_flush(); // display方式. 直接输出页面
        } else {
            ob_end_clean(); // render. 获取页面内容,不直接输出
        }

        return 1;
    }

    /**
     * @param $tpl_vars
     * @param $vars
     * @return array
     */
    private function buildSymtable($tpl_vars, $vars): ?array
    {
        $symbol_table = [];
        $scope        = $this;

        if ($tpl_vars && is_array($tpl_vars)) {
            foreach ($tpl_vars as $var_name => $entry) {
                /* GLOBALS protection */
                if ($var_name === 'GLOBALS') {
                    continue;
                }

                if ($var_name === 'this' && $scope && !empty($scope->name)) {
                    continue;
                }

                if ($this->validVarName($var_name, strlen($var_name))) {
                    $symbol_table[$var_name] = $entry;
                }
            }
        }

        if (!empty($vars) && is_array($vars)) {
            foreach ($vars as $var_name => $entry) {
                /* GLOBALS protection */
                if ($var_name === 'GLOBALS') {
                    continue;
                }

                if ($var_name === 'this' && $scope && !empty($scope->name)) {
                    continue;
                }

                if ($this->validVarName($var_name, strlen($var_name))) {
                    $symbol_table[$var_name] = $entry;
                }
            }
        }

        return $symbol_table;
    }

    /**
     * TODO
     *
     * @param string $tpl
     * @param array|null $vars
     * @param $result
     * @return int
     */
    private function simpleEval(string $tpl, ?array $vars, &$result): int
    {
        if (!is_string($tpl)) {
            return 0;
        }

        $tpl_vars = $this->_tpl_vars;
        $symbol_table = $this->buildSymtable($tpl_vars, $vars);

        if (strlen($tpl)) {
            $phtml = sprintf("?>%s", $tpl);
            // TODO zend_compile_string
        }

        return 1;
    }

    /**
     * @param string $var_name
     * @param int $var_name_len
     * @return int
     */
    private static function validVarName(string $var_name, int $var_name_len): int
    {
        if (empty($var_name)) {
            return 0;
        }

        /* 只允许首字符为: [a-zA-Z_\x7f-\xff] */
        $ch = ord($var_name[0]);
        if ($var_name[0] != '_' &&
            ($ch < 65  /* A    */ || /* Z    */ $ch > 90)  &&
            ($ch < 97  /* a    */ || /* z    */ $ch > 122) &&
            ($ch < 127 /* 0x7f */ || /* 0xff */ $ch > 255)
        ) {
            return 0;
        }

	    /* And these as the rest: [a-zA-Z0-9_\x7f-\xff] */
	    if ($var_name_len > 1) {
            for ($i = 1; $i < $var_name_len; $i++) {
                $ch = ord($var_name[$i]);

                if ($var_name[$i] != '_' &&
                    ($ch < 48  /* 0    */ || /* 9    */ $ch > 57)  &&
                    ($ch < 65  /* A    */ || /* Z    */ $ch > 90)  &&
                    ($ch < 97  /* a    */ || /* z    */ $ch > 122) &&
                    ($ch < 127 /* 0x7f */ || /* 0xff */ $ch > 255)
                ) {
                    return 0;
                }
            }
        }

	    return 1;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->_tpl_vars);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($name, $value)
    {
        return $this->assign($name, $value);
    }
}