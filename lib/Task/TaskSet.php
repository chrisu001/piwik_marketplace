<?php
use Symfony\Component\Console\Input\ArrayInput;
/**
 *  *
 * PluginMarketplace
 *
 * Copyright (c) 2012-2013, Christian Suenkel <info@suenkel.org>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * * Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in
 *   the documentation and/or other materials provided with the
 *   distribution.
 *
 * * Neither the name of Christian Suenkel nor the names of his
 *   contributors may be used to endorse or promote products derived
 *   from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Christian Suenkel <christian@suenkel.de>
 * @link http://plugin.suenkel.org
 * @copyright 2012-2013 Christian Suenkel <info@suenkel.de>
 * @license http://www.opensource.org/licenses/BSD-3-Clause The BSD 3-Clause License
 *
 * @category Piwik_Plugins
 * @package PluginMarketplace
 */
require_once dirname(__FILE__) . '/Processor.php';
/**
 * Container of a sequence of tasks to be executed stepwise
 *
 * @package PluginMarketplace
 */
class Marketplace_TaskSet
{
    /**
     * the parent processor
     *
     * @var Marketplace_Processor
     */
    protected $processor;
    /**
     * the taks
     *
     * @var array
     */
    protected $taskSet = array();
    protected $currentTask = 0;
    protected $name = '';
    /**
     * the runtime configuration to be processed by the tasks
     *
     * @var Array
     */
    protected $config = array();
    protected $loghistory = array();
    /**
     * indicator, if there exists a task, which cannot be executed in step mode
     *
     * @var bool
     */
    protected $hasDeferred = false;
    protected $id = null;
    /**
     * status of a task
     *
     * @var int
     */
    const STATUS_SETUP = 0;
    const STATUS_DONE = 1;
    const STATUS_ERROR = -1;

    public function __construct($name, $config = array(), Marketplace_Processor $processor = null)
    {
        $this->processor = $processor;
        $this->name = $name;
        $this->config = $config;
        $this->id = uniqid();
    }

    public function loop()
    {
        $this->currentTask = 0;
        $this->hasDeferred = false;
        while (true == $this->step(true)) {
        }
        return $this;
    }

    public function step($execDeferredAlso = false)
    {
        // find a task to be executed
        foreach ($this->taskSet as $idx => $taskdef) {
            $this->currentTask = $idx;
            
            if ($taskdef['status'] == self::STATUS_ERROR) {
                // stop process, task has error
                $this->currentTask = -1;
                return false;
            }
            
            if ($taskdef['status'] == self::STATUS_DONE) {
                continue;
            }
            
            if ($execDeferredAlso || !$this->executeTaskMethod('isDeferred')) {
                $this->executeTask($idx);
                return true;
            }
            // deferred task found, so wait until loop() is called
            $this->hasDeferred = true;
            return false;
        }
        // all done
        $this->currentTask = -1;
        return false;
    }

    protected function executeTask()
    {
        try {
            $this->executeTaskMethod('setUp');
            $this->executeTaskMethod('execute');
            $this->executeTaskMethod('tearDown');
            $this->taskSet[$this->currentTask]['status'] = self::STATUS_DONE;
        } catch (Exception $e) {
            $this->taskSet[$this->currentTask]['status'] = self::STATUS_ERROR;
            $this->taskSet[$this->currentTask]['error'] = $e->getMessage();
        }
        $this->log('finished');
        return $this;
    }

    protected function executeTaskMethod($methodname, $params = array())
    {
        $this->log('execute ' . $methodname);
        return call_user_func(array($this->taskSet[$this->currentTask]['task'], $methodname), 
                $params);
    }

    public function log($msg)
    {
        if (isset($this->taskSet[$this->currentTask])) {
            $msg = sprintf('Task %s (%d): %s', 
                    $this->taskSet[$this->currentTask]['task']->getName(), 
                    $this->taskSet[$this->currentTask]['status'], $msg);
        }
        $this->loghistory[] = $msg;
        return $this;
    }

    public function pushTaskByClassName($classname)
    {
        $task = new $classname($this);
        return $this->pushTask($task);
    }

    public function pushTask(Marketplace_TaskAbstract $task)
    {
        $taskdef = array('name' => $task->getName(), 
                'status' => self::STATUS_SETUP, 
                'task' => $task, 
                'error' => '');
        array_push($this->taskSet, $taskdef);
        return $this;
    }

    public function isFinished()
    {
        return ($this->currentTask == -1);
    }

    public function getStatus()
    {
        $idx = -1;
        $itemStatus = array('step' => 0, 
                'task' => 'no task', 
                'status' => self::STATUS_DONE, 
                'error' => '');
        
        $setStatus = array('id' => $this->id, 
                'name' => $this->name, 
                'steps' => count($this->taskSet), 
                'debug' => $this->config, 
                'history' => $this->loghistory);
        
        // find first unfinished task
        foreach ($this->taskSet as $idx => $taskdef) {
            $itemStatus = array('step' => $idx + 1, 
                    'task' => $taskdef['task']->getName(), 
                    'status' => $taskdef['status'], 
                    'error' => $taskdef['error']);
            if ($taskdef['status'] != self::STATUS_DONE) {
                break;
            }
        }
        return array_merge($setStatus, $itemStatus);
    }

    public function getConfig($key = null, $defaultValue = null)
    {
        if ($key == null) {
            return $this->config;
        }
        return isset($this->config[$key]) ? $this->config[$key] : $defaultValue;
    }

    public function setConfig($key = null, $value = null)
    {
        if ($key == null) {
            $this->config = $value;
        } else {
            $this->config[$key] = $value;
        }
        return $this;
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function isDeferred()
    {
        return $this->hasDeferred;
    }

    public function setProcessor(Marketplace_Processor $processor)
    {
        $this->processor = $processor;
        return $this;
    }

    public function removeTaskByTaskName($taskname)
    {
        return $this->replaceTaskByTaskName($taskname, null);
    }

    public function replaceTaskByTaskName($taskname, Marketplace_TaskAbstract $exchange = null)
    {
        foreach ($this->taskSet as $idx => $task) {
            if ($task['task']->getName() !== $taskname) {
                continue;
            }
            // taskname match, delete, if status == setup
            if ($task['status'] !== self::STATUS_SETUP) {
                throw new RuntimeException(
                        'only tasks with status = setup might be removed/exchanged');
            }
            if ($exchange) {
                $this->taskSet[$idx];
            } else {
                unset($this->taskSet[$idx]);
            }
        }
        return $this;
    }

    public function __sleep()
    {
        return array('taskSet', 'config', 'name', 'processor', 'id', 'hasDeferred');
    }
}