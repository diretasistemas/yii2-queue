<?php
/**
 * Created by PhpStorm.
 * User: Nikolay
 * Date: 29.06.2016
 * Time: 15:46
 */

namespace yii\queue\components;


use yii\base\Component;
use yii\queue\exceptions\WorkerException;
use yii\queue\interfaces\WorkerInterface;
use yii\queue\models\MessageModel;

/**
 * Class WorkerComponent
 * @package yii\queue
 *
 * @property $actionClass \yii\console\Controller
 * @property $_message MessageModel
 */
class WorkerComponent extends Component implements WorkerInterface
{

    public $action;
    public $workerName;
    public static $actionClassName = '';
    public static $isRun = false;

    private $_actionClass = null;
    private $_message = null;
    private $_watcherId = null;
    private $_validClassMethods = [];

    public function init()
    {
        parent::init();
        $this->setActionClass($this->constructActionClass($this->action));
    }

    private function constructActionClass($action)
    {
        $actionClass = null;
        try {
            if (is_array($action) && isset($action['class'])) {
                $actionClass = new $action['class']($this->workerName, $this);
            } elseif (class_exists($action)) {
                $actionClass = new $action();
            }
        } catch (WorkerException $wEx) {
            throw new $wEx('Can\'t create action class!');
        }
        return $actionClass;
    }

    public function setActionClass($class = null)
    {
        if (is_object($class)) {
            $this->_actionClass = $class;
            $this::$actionClassName = get_class($class);
            return true;
        }
        return false;
    }

    public function getActionClass()
    {
        return $this->_actionClass;
    }

    public function setWatcherId($watcherId = null)
    {
        $this->_watcherId = $watcherId;
    }

    public function getWatcherId()
    {
        return $this->_watcherId;
    }

    public function setMessage(MessageModel $messageModel)
    {
        $this->_message = $messageModel;
        if (empty($this::$actionClassName) && !empty($messageModel->actionClassName)) {
            if (!$this->setActionClass($this->constructActionClass($messageModel->actionClassName))) {
                throw new WorkerException("Not exist action class!");
            }
        }
        return true;
    }

    public function getMessage()
    {
        return $this->_message;
    }

    private function methodInClassValidate($class, $method)
    {
        $classHasMethod = false;

        if (isset($this->_validClassMethods[$class]) && isset($this->_validClassMethods[$class][$method])) {
            $classHasMethod = true;
        } elseif (method_exists($class, $method)
            && is_callable([$class, $method])
        ) {
            $this->_validClassMethods[$class] = [];
            $this->_validClassMethods[$class][$method] = new \ReflectionMethod($class, $method);;

            $classHasMethod = true;
        }

        return $classHasMethod;
    }

    /**
     * @param $class
     * @param $method
     * @param $arguments
     * @return bool
     * @throws WorkerException
     * @var \ReflectionFunction $refFunc
     */
    private function argumentsValidate($class, $method, $arguments)
    {
        if (isset($this->_validClassMethods[$class]) && isset($this->_validClassMethods[$class][$method])) {
            $refFunc = $this->_validClassMethods[$class][$method];
            $userArguments = array_keys($arguments);
            $missingArguments = [];
            foreach ($refFunc->getParameters() as $param) {
                if (!$param->isOptional() && !in_array($param->getName(), $userArguments)) {
                    $missingArguments[] = $param->getName();
                } else {
                    if ($param->getClass()
                        && (
                            !is_object($arguments[$param->getName()])
                            || get_class($arguments[$param->getName()]) !== $param->getClass()->name
                        )
                    ) {
                        throw new WorkerException(
                            "Method `{$method}` param `{$param->getName()}` " .
                            "expects type `{$param->getClass()->name}` but got " . gettype($arguments[$param->getName()])
                        );
                    }
                }
            }
            if (sizeof($missingArguments)) {
                throw new WorkerException(
                    "Method `{$method}` missing required arguments: " . implode(
                        ', ',
                        $missingArguments
                    )
                );
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return mixed
     * @throws WorkerException
     */
    public function run()
    {
        $message = $this->getMessage();
        if ($this->methodInClassValidate($this::$actionClassName, $message->method)) {
            if ($this->argumentsValidate($this::$actionClassName, $message->method, $message->arguments)) {
                $namespace = $this::$actionClassName;
                $job = new $namespace('jobs', 'jobs', []);
                $job->{$message->method}($message->arguments[0]);
                return true;
            } else {
                return false;
            }
            // return $this->argumentsValidate($this::$actionClassName, $message->method, $message->arguments) ? call_user_func_array(array($this::$actionClassName, $message->method), $message->arguments) : false;
        } else {
            throw new WorkerException("Method `{$message->method}` not exist in class `{$this::$actionClassName}`");
        }
    }

    /**
     * Stop worker
     */
    public function stop()
    {
        if ($watcherId = $this->getWatcherId()) {
            \Amp\cancel($watcherId);
        }
    }

    /**
     * Set Options
     *
     * @param array $options
     */
    public function setOptions(array $options)
    {
        if (!empty($options)) {
            foreach ($options as $optionName => $optionValue) {
                $this->{$optionName} = $optionValue;
            }
        }
    }
}