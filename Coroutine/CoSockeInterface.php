<?php

namespace Async\Coroutine;

interface CoSocketInterface 
{
    public function accept();
	
    public function read(int $size);

    public function write(string $string);

    public function close();
}
