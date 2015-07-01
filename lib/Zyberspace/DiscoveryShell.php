<?php
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

        return $matchingMethods;
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
        $arguments = array_map(function($value) {
            return $value->value->value; //\PhpParser\Node\Arg -> \PhpParser\Node\Scalar\[...] -> value
        }, $statements[0]->args);

        return array($method, $arguments);
    }

    protected function _outputAnswer($answer)
    {
        var_dump($answer);
    }
}
