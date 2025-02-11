<?php
namespace mojifan\redis;

use Yii;
use yii\di\Instance;

/**
 * Redis Cache implements a cache application component based on [redis](http://redis.io/) key-value store.
 *
 * Redis Cache requires redis version 2.6.12 or higher to work properly.
 *
 * It needs to be configured with a redis [[Connection]] that is also configured as an application component.
 * By default it will use the `redis` application component.
 *
 * See [[Cache]] manual for common cache operations that redis Cache supports.
 *
 * Unlike the [[Cache]], redis Cache allows the expire parameter of [[set]], [[add]], [[mset]] and [[madd]] to
 * be a floating point number, so you may specify the time in milliseconds (e.g. 0.1 will be 100 milliseconds).
 *
 * To use redis Cache as the cache application component, configure the application as follows,
 *
 * ~~~
 * [
 *     'components' => [
 *         'cache' => [
 *             'class' => 'mojifan\redis\Cache',
 *         ],
 *     ],
 * ]
 * ~~~
 *
 * Or if you have configured the redis [[Connection]] as an application component, the following is sufficient:
 *
 * ~~~
 * [
 *     'components' => [
 *         'cache' => [
 *             'class' => 'mojifan\redis\Cache',
 *             // 'redis' => 'redis' // id of the connection application component
 *         ],
 *     ],
 * ]
 * ~~~
 *
 */
class Cache extends \yii\caching\Cache
{
    /**
     * @var Connection|string|array the Redis [[Connection]] object or the application component ID of the Redis [[Connection]].
     * This can also be an array that is used to create a redis [[Connection]] instance in case you do not want do configure
     * redis connection as an application component.
     * After the Cache object is created, if you want to change this property, you should only assign it
     * with a Redis [[Connection]] object.
     */
    public $redis = 'redis';


    /**
     * Initializes the redis Cache component.
     * This method will initialize the [[redis]] property to make sure it refers to a valid redis connection.
     * @throws InvalidConfigException if [[redis]] is invalid.
     */
    public function init()
    {
        parent::init();
        $this->redis = Instance::ensure($this->redis, Connection::className());
    }

    /**
     * Checks whether a specified key exists in the cache.
     * This can be faster than getting the value from the cache if the data is big.
     * Note that this method does not check whether the dependency associated
     * with the cached data, if there is any, has changed. So a call to [[get]]
     * may return false while exists returns true.
     * @param mixed $key a key identifying the cached value. This can be a simple string or
     * a complex data structure consisting of factors representing the key.
     * @return boolean true if a value exists in cache, false if the value is not in the cache or expired.
     */
    public function exists($key)
    {
        return (bool)$this->redis->exists($this->buildKey($key));
    }

    /**
     * @inheritdoc
     */
    protected function getValue($key)
    {
        $data = $this->redis->get($key);
        return $data === null ? false : $data;
    }

    /**
     * @inheritdoc
     */
    protected function getValues($keys)
    {
        $response = $this->redis->mget($keys);
        $result = [];
        $i = 0;
        foreach ($keys as $key) {
            $result[$key] = $response[$i++];
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function setValue($key, $value, $expire)
    {
        if ($expire == 0) {
            return (bool)$this->redis->set($key, $value);
        } else {
            $expire = (int)($expire * 1000);

            return (bool)$this->redis->set($key, $value, 'PX', $expire);
        }
    }

    /**
     * @inheritdoc
     */
//    protected function setValues($data, $expire)
//    {
//        $args = [];
//        foreach ($data as $key => $value) {
//            $args[] = $key;
//            $args[] = $value;
//        }
//
//        $failedKeys = [];
//        if ($expire == 0) {
//            $this->redis->mset($args);
//        } else {
//            $expire = (int) ($expire * 1000);
//            $this->redis->multi();
//            $this->redis->mset($args);
//            $index = [];
//            foreach ($data as $key => $value) {
//                $this->redis->pexpire($key, $expire);
//                $index[] = $key;
//            }
//            $result = $this->redis->exec();
//            array_shift($result);
//            foreach ($result as $i => $r) {
//                if ($r != 1) {
//                    $failedKeys[] = $index[$i];
//                }
//            }
//        }
//
//        return $failedKeys;
//    }

    /**
     * @inheritdoc
     */
    protected function addValue($key, $value, $expire)
    {
        if ($expire == 0) {
            return (bool)$this->redis->set($key, $value, 'NX');
        } else {
            $expire = (int)($expire * 1000);

            return (bool)$this->redis->set($key, $value, 'PX', $expire, 'NX');
        }
    }

    /**
     * @inheritdoc
     */
    protected function deleteValue($key)
    {
        return (bool)$this->redis->del($key);
    }

    /**
     * @inheritdoc
     */
    protected function flushValues()
    {
        return $this->redis->flushdb();
    }
}