<?php


class TokenBucketTest extends PHPUnit_Framework_TestCase
{

    public function testIsThereAnySyntaxError()
    {
        $var = new Snelling\TokenBucket(new Snelling\Redis());
        $this->assertTrue(is_object($var));
        unset($var);
    }
}