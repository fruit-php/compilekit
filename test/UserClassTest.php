<?php

namespace FruitTest\CompileKit;

use Fruit\CompileKit\UserClass;
use Fruit\CompileKit\UserMethod;

class UserClassTest extends \PHPUnit\Framework\TestCase
{
    public function testFull()
    {
        $c = (new UserClass('X'))
            ->extends('A')
            ->implements('B', 'C')
            ->const('C1', '2')
            ->use('D', 'E')
            ->prop('p1')
            ->prop('p2', 'private', true, '3')
            ->method(new UserMethod('m1'))
            ->method((new UserMethod('m1', 'private', true)));

        $expect = 'class X extends A implements B,C{use D;use E;const C1 = 2;public $p1;private static $p2 = 3;public function m1() {}private static function m1() {}}';
        $actual = $c->render();

        $this->assertEquals($expect, $actual);
    }

    public function testFullPretty()
    {
        $c = (new UserClass('X'))
            ->extends('A')
            ->implements('B', 'C')
            ->const('C1', '2')
            ->prop('p1')
            ->prop('p2', 'private', true, '3')
            ->use('D', 'E')
            ->method(new UserMethod('m1'))
            ->method((new UserMethod('m1', 'private', true)));

        $expect = 'class X extends A implements
    B,
    C
{
    use D;
    use E;

    const C1 = 2;

    public $p1;
    private static $p2 = 3;

    public function m1()
    {

    }

    private static function m1()
    {

    }
}';
        $actual = $c->render(true);

        $this->assertEquals($expect, $actual);
    }

    public function testFullPrettyIndent()
    {
        $c = (new UserClass('X'))
            ->extends('A')
            ->implements('B', 'C')
            ->const('C1', '2')
            ->prop('p1')
            ->prop('p2', 'private', true, '3')
            ->use('D', 'E')
            ->method(new UserMethod('m1'))
            ->method((new UserMethod('m1', 'private', true)));

        $expect = 'class X extends A implements
    B,
    C
{
    use D;
    use E;

    const C1 = 2;

    public $p1;
    private static $p2 = 3;

    public function m1()
    {

    }

    private static function m1()
    {

    }
}';
        $actual = $c->render(true, 1);

        $this->assertEquals($expect, $actual);
    }

    public function testGetMethod()
    {
        $c = new UserClass('A');
        $expect = $c->can('b');
        $c->can('c');
        $actual = $c->getMethod('b');
        $this->assertEquals($expect, $actual);
    }
}
