<?php

/*
 * This file is part of the Maillocal package.
 *
 * Copyright 2019 Jonathan Foucher
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @package Mailocal
 */

namespace App\Email;

use MS\Email\Parser\Attachment;
use MS\Email\Parser\Parser as BaseParser;
use Html2Text\Html2Text;
use MS\Email\Parser\Part;

class Parser extends BaseParser
{
    protected function getTextContent($html)
    {
        $txt = new Html2Text(quoted_printable_decode($html));
        return preg_replace(
            '/^[\s]+/m',
            '',
            trim($txt->getText(), " \t\n\r ".urldecode("%C2%A0"))
        );
    }
}
