<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\queue\drivers;

use \yii\queue\interfaces\DriverInterface;

/**
 * RedisConnection
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class RedisConnection extends \yii\base\Component implements DriverInterface
{
	public function delete(array $message)
	{
		
	}

	public function pop($queue)
	{
		
	}

	public function purge($queue)
	{
		
	}

	public function push($payload, $queue, $delay = 0)
	{
		
	}

	public function release(array $message, $delay = 0)
	{
		
	}

}
