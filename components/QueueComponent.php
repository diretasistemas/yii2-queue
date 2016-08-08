<?php
/**
 * Created by PhpStorm.
 * User: Nikolay
 * Date: 29.06.2016
 * Time: 15:08
 */

namespace yii\queue\components;

use yii\base\Component;
use yii\di\ServiceLocator;
use yii\queue\exceptions\QueueException;
use yii\queue\interfaces\QueueInterface;
use yii\queue\models\MessageModel;

/**
 * Main component of Yii queue
 *
 * Class QueueComponent
 * @package yii\queue\components
 * @property $regChannels ServiceLocator
 * @property $regWorkers ServiceLocator
 */
class QueueComponent extends Component implements QueueInterface
{
    public $queueName = 'queue';
    public $channels = [];
    public $workers = [];
    public $timeout = 1000;

    protected $regWorkers = null;
    protected $regChannels = null;

    private $_pid = null;

    public function init()
    {
        parent::init();

        if (!empty($this->channels)) {

            $this->regChannels = new ServiceLocator();

            foreach ($this->channels as $channelName => $channel) {
                $channel['queueName'] = $this->queueName;
                $channel['channelName'] = $channelName;
                $this->regChannels->set($channelName, $channel);
            }

        } else throw new QueueException("Empty channels!");

        if (!empty($this->workers)) {

            $this->regWorkers = new ServiceLocator();
            foreach ($this->workers as $workerName => $worker) {
                $channel['workerName'] = $workerName;
                $this->regWorkers->set($workerName, $worker);
            }

        } else throw new QueueException("Empty workers!");

    }

    /**
     * @param string $name
     * @return ChannelComponent
     * @throws QueueException
     */
    public function getChannel($name = '')
    {
        $name = empty($name) ? ($this->getChannelNamesList()[0] ?: '') : $name;

        if (isset($this->channels[$name])) {
            return $this->regChannels->{$name};
        } else {
            throw new QueueException("Channel `{$name}` not exist! Pls configure it before usage.");
        }
    }

    /**
     * @param $name
     * @return mixed
     * @throws QueueException
     */
    public function getWorker($name)
    {
        $name = empty($name) ? ($this->getChannelNamesList()[0] ?: '') : $name;

        if (isset($this->workers[$name])) {
            return $this->regWorkers->get($name);
        } else {
            throw new QueueException("Worker $name not exist!");
        }
    }

    public function getChannelNamesList()
    {
        return array_keys($this->channels);
    }

    public function getWorkerNamesList()
    {
        return array_keys($this->workers);
    }

    public function setPid($pid)
    {
        $this->_pid = $pid;
    }

    public function getPid()
    {
        return $this->_pid;
    }

    /**
     * @param MessageModel $messageModel
     * @return bool
     * @var $worker
     */
    public function processMessage(MessageModel $messageModel, $watcherId = null)
    {
        /** @var WorkerComponent $worker */
        if ($worker = $this->getWorker($messageModel->worker)) {
            $worker->setMessage($messageModel);
            $worker->setWatcherId($watcherId);
            $worker->run();
        }
    }

    /**
     * @var $message MessageModel
     */
    public function startDaemon()
    {
        \Amp\run(function () {
            $this->setPid(getmypid());

            // Checking if Unix, so it can use the SIGINT
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                \Amp\onSignal(SIGINT, function () use (&$worker) {
                    echo "Worker {$this->getPid()} Terminated";
                    \Amp\stop();
                });
            }

            foreach ($this->getChannelNamesList() as $channelName) {
                $channel = $this->getChannel($channelName);
                $i = 0;
                \Amp\repeat(function ($watcherId) use ($channel, &$i) {
                    if ($message = $channel->pop()) {
                        $this->processMessage($message, $watcherId);
                        return true;
                    } else {
                        return false;
                    }
                }, $this->timeout);
            }

        });
    }
}