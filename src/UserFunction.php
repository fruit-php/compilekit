<?php

namespace Fruit\CompileKit;

/**
 * UserFunction is a helper to define your own function.
 *
 * It supports both named and anonymouse functions.
 *
 *     $f = new UserFunction('f');
 *     $f->accept('a')->type('string');
 *     $f->accept('b')->type('int')->var(0);
 *     $f
 *         ->return('string')
 *         ->line('$ret = $a . $b;')
 *         ->line('return $ret;');
 *     $f->render();
 *     // function f(string $a, int $b = 0) {$ret = $a . $b;return $ret;}
 *     $f->render(true);
 *     // function f(string $a, int $b = 0)
 *     // {
 *     //     $ret = $a . $b;
 *     //     return $ret;
 *     // }
 */
class UserFunction implements Renderable
{
    private $name;
    private $args = [];
    private $returnType = '';
    private $body;
    private $use = [];
    private $wrapAt = 5;

    /**
     * Provide readonly access to private properties.
     */
    public function __get(string $name)
    {
        if (in_array($name, ['name', 'returnType', 'body'])) {
            return $this->$name;
        }

        trigger_error($name . ' is not a valid property of UserFunction');
    }

    protected function restore(array $data)
    {
        $this->args = $data['args'];
        $this->returnType = $data['returnType'];
        $this->body = $data['body'];
        $this->use = $data['use'];
    }

    public static function __set_state(array $data)
    {
        $ret = new self();
        foreach ($data as $k => $v) {
            $ret->$k = $v;
        }
        return $ret;
    }

    /**
     * Wrap line if number of args >= $num ($num must >= 1)
     *
     * By default, we wrap line if number of args >= 5
     */
    public function wrapArg(int $num): self
    {
        if ($num < 1) {
            return $this;
        }

        $this->wrapAt = $num;
        return $this;
    }

    /**
     * The constructor.
     *
     * Pass the name of the function here. Use empty string for anonymous function.
     * Name is NOT validated, might be supported in later version.
     *
     * @param $name string function name, use empty string for anonymous function.
     */
    public function __construct(string $name = '')
    {
        $this->name = $name;
        $this->body = new Block;
    }

    /**
     * Set inherited parameters.
     *
     * Only anonymous function will use this. Named function will ignore it.
     *
     * @param $vars string variable names to inherit
     * @return self
     */
    public function use(string ...$vars): self
    {
        foreach ($vars as $v) {
            if ($v[0] !== '$') {
                $v = '$' . $v;
            }
            array_push($this->use, $v);
        }

        return $this;
    }

    /**
     * Add an argument to the function.
     *
     *     $f->accept('a')->type('string');      // function (string $a)
     *     $f->accept('b')->type('int')->var(0); // function (string $a, int $b = 0)
     *
     * @see UserFunction::arg
     * @param $name string argument name
     * @return Argument instance.
     */
    public function accept(string $name): Argument
    {
        $ret = new Argument($name);
        array_push($this->args, $ret);

        return $ret;
    }

    /**
     * Setting function arguments.
     *
     * This is helper method for UserFunction::accept.
     *
     *     // generates function (string $a, int $b = 0)
     *     $userFunc->rawArg('$a', 'string')->rawArg('b', 'int', '0');
     *
     * You have to pass PHP SOURCE CODE to the $default. You can do it easily with
     * `var_export()` for primitive values.
     *
     *     $userFunc->rawArg('a', 'int', var_export(1, true));
     *
     * @see UserFunction::accept
     * @see UserFunction::bindArg
     * @param $name string name of the argument.
     * @param $type string type hint of the argument. (optional)
     * @param $default string PHP SOURCE CODE of default value of the argument. (optional)
     * @return self
     */
    public function rawArg(string $name, string $type = '', string $default = ''): self
    {
        $this->accept($name)
            ->type($type)
            ->rawDefault($default);

        return $this;
    }

    /**
     * Setting function arguments.
     *
     * This is helper method for UserFunction::accept.
     *
     *     // generates function (int $a = 1)
     *     $userFunc->bindArg('a', 1, 'int')->bindArg('b', true);
     *
     * WARNING: Order of arguments between rawArg and bindArg is different.
     *
     * @param $name string name of the argument.
     * @param $default string default value of the argument. MUST COMPITABLE WITH var_export().
     * @param $type string type hint of the argument. (optional)
     * @return self
     */
    public function bindArg(string $name, $default, string $type = ''): self
    {
        $this->accept($name)
            ->type($type)
            ->bindDefault($default);

        return $this;
    }

    /**
     * Define return type
     *
     * @param $type string return type
     * @return self
     */
    public function return(string $type): self
    {
        $this->returnType = $type;

        return $this;
    }

    /**
     * Append a line of php code to function body.
     *
     * @param $line string php code
     * @return self
     */
    public function line(string $line): self
    {
        $this->body->line($line);

        return $this;
    }

    /**
     * Append multiple lines of php code to function body.
     *
     * @param $block string array of php codes
     * @return self
     */
    public function block(array $block): self
    {
        $this->body->line(...$block);

        return $this;
    }

    /**
     * Append a block of php code to function body.
     *
     * @param $block string array of php codes
     * @return self
     */
    public function append(Block $block): self
    {
        $this->body->append($block);

        return $this;
    }

    /**
     * @see Renderable
     * @param $pretty bool true to generate multi-line code, default to false
     * @param $indent int indent level, used if $pretty is true
     * @return string of generated php code.
     */
    public function render(bool $pretty = false, int $indent = 0): string
    {
        $lf = '';
        $str = '';
        if ($pretty) {
            if ($indent < 0) {
                $indent = 0;
            }
            $lf = "\n";
            $str = str_repeat(' ', $indent * 4);
        }
        $ret = $this->returnType;
        if ($this->returnType !== '') {
            $ret = ': ' . $ret;
        }

        if ($indent < 0) {
            $indent = 0;
        }

        $argc = count($this->args);
        $nowrap = ($argc >= $this->wrapAt or $this->name === '');
        $i = 0;
        if ($argc >= $this->wrapAt) {
            $i = $indent + 1;
        }
        $args = array_map(function ($a) use ($pretty, $i) {
            return $a->render($pretty, $i);
        }, $this->args);

        $usestr = '';
        if ($this->name === '' and count($this->use) > 0) {
            $str2 = '';
            if ($pretty) {
                $str2 = '    ';
            }
            $usestr = ' use ('
                . implode(', ', $this->use)
                . ')';
        }

        $lfArg = '';
        $strArg = '';
        $neck = $lf;
        $sp = ', ';
        if ($pretty and $argc >= $this->wrapAt) {
            $lfArg = $lf;
            $strArg = $str;
            $sp = ',' . $lf;
        }
        if (!$pretty or ($pretty and $nowrap)) {
            $neck = ' ';
        }

        return $str . 'function ' . $this->name . '(' . $lfArg
            . implode($sp, $args) . $lfArg
            . $strArg . ')' . $usestr . $ret . $neck
            . $this->renderBody($pretty, $indent, $nowrap);
    }

    private function renderBody(bool $pretty, int $indent, bool $oneline): string
    {
        if (!$pretty) {
            return '{' . $this->body->render() . '}';
        }

        $str = str_repeat(' ', $indent * 4);
        $head = $str;
        if ($oneline) {
            $head = '';
        }

        return $head . "{\n"
            . $this->body->render($pretty, $indent+1)
            . "\n" . $str . '}';
    }
}
