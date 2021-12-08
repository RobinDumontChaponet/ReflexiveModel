<?php

namespace Reflexive\Core;

class ModelException extends \Exception
{
    public function getMessages(): array
    {
        $messages = [$this->getMessage()];

        if($this->getPrevious())
            $messages = array_merge($this->getPrevious()->getMessages(), $messages);

        return $messages;
    }

    public function __toString(): string
    {
        $str = ''.$this->getPrevious().PHP_EOL;
        $str .= ($str) ? PHP_EOL : '';

        $str .= $this->getMessage();

        return $str;
    }

    public static function throw(self $e = null) {
        if(isset($e))
            throw $e;
    }
}
