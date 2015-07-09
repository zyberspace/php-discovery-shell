<?php
/**
 * Copyright 2015 Eric Enold <zyberspace@zyberware.org>
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Zyberspace;

class DiscoveryShell
{
    protected $_object;
    protected $_objectVariableName;
    protected $_parser;

    protected $_historyFile = false;

    public function __construct($object, $objectVariableName, $historyFile = false)
    {
        $this->_object = $object;
        $this->_objectVariableName = $objectVariableName;

        $this->_parser = new \PhpParser\Parser(new \PhpParser\Lexer);

        if ($historyFile !== false) {
            $this->_historyFile = realpath($historyFile);
        }
    }

    public function run()
    {
        $this->_registerCompletionFunction();
        $this->_loadHistory();
        $this->_runShell();
    }

    /**
     * Registers the completion-function for readline
     */
    protected function _registerCompletionFunction()
    {
        readline_completion_function(array($this, '_completionFunction'));
    }

    protected function _completionFunction($toComplete, $toCompletePosition, $toCompleteLength) {
        if ($toCompletePosition !== 0) { // only complete method-names
            return false;
        }
        $reflectionClass = new \ReflectionClass($this->_object);
        $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

        $matchingMethods = array();
        foreach ($methods as $method) {
            $methodName = $method->name;

            if (
                substr($methodName, 0, 2) !== '__'
                && substr($methodName, 0, $toCompleteLength) === $toComplete
            ) {
                $parameters = array();
                foreach ($method->getParameters() as $parameter) {
                    $parameters[] = '$' . $parameter->name;
                }

                $matchingMethods[] = $methodName . '(' . implode(', ', $parameters) . ');';
            }
        }

        return count($matchingMethods) > 0 ? $matchingMethods : null;
    }

    protected function _loadHistory()
    {
        if ($this->_historyFile !== false && is_readable($this->_historyFile)) {
            readline_read_history($this->_historyFile);
        }
    }

    protected function _writeHistory()
    {
        if ($this->_historyFile !== false && is_writeable($this->_historyFile)) {
            readline_write_history($this->_historyFile);
        }
    }

    /**
     * Runs the shell
     */
    protected function _runShell()
    {
        echo wordwrap('-- discovery-shell to help discover a class or library --' . PHP_EOL . PHP_EOL
            . 'Use TAB for autocompletion and your arrow-keys to navigate through your method-history.' . PHP_EOL
            . 'Beware! This is not a full-featured php-shell. The input gets parsed with PHP-Parser to avoid the usage'
            . ' of eval().' . PHP_EOL, exec('tput cols'), PHP_EOL);

        while (true) {
            $input = $this->_getInput();

            list($method, $arguments) = $this->_parseInput($input);

            $answer = call_user_func_array(array($this->_object, $method), $arguments);
            $this->_outputAnswer($answer);
        }
    }

    protected function _getInput()
    {
        $input = readline('> $' . $this->_objectVariableName . '->');
        readline_add_history($input);
        $this->_writeHistory();

        return $input;
    }

    protected function _parseInput($input)
    {
        $statements = $this->_parser->parse('<?php ' . $input . ';');

        $method = $statements[0]->name->toString();
        $arguments = $this->_parseArgumentNodes($statements[0]->args);

        return array($method, $arguments);
    }

    protected function _parseArgumentNodes($argumentNodes)
    {
        $arguments = array();
        foreach ($argumentNodes as $argumentNode) {
            $argumentNodeValue = $argumentNode->value;

            $argument = null; //null if we don't support the node-type of the argument
            if (
                $argumentNodeValue instanceof \PhpParser\Node\Scalar\DNumber
                || $argumentNodeValue instanceof \PhpParser\Node\Scalar\LNumber
                || $argumentNodeValue instanceof \PhpParser\Node\Scalar\String_
            ) {
                $argument = $argumentNodeValue->value;
            } else if ($argumentNodeValue instanceof \PhpParser\Node\Expr\ConstFetch) {
                switch (strtolower($argumentNodeValue->name->parts[0])) {
                    case 'true':
                        $argument = true;
                        break;
                    case 'false':
                        $argument = false;
                        break;
                }
            } else if ($argumentNodeValue instanceof \PhpParser\Node\Expr\Array_) {
                $argument = $this->_parseArgumentNodes($argumentNodeValue->items);
            }

            if (isset($argumentNode->key) && $argumentNode->key !== NULL) { //if $argumentNodes is an array-node
                $arguments[$argumentNode->key->value] = $argument;
            } else {
                $arguments[] = $argument;
            }
        }

        return $arguments;
    }

    protected function _outputAnswer($answer)
    {
        var_dump($answer);
    }
}
