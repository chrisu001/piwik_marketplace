<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://plugin.suenkel.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @author Christian Suenkel <christian@suenkel.de>
 *
 * @category Piwik_Plugins
 * @package  Piwik_PluginMarketplace
 */

/**
 * Include the Cache
 */
require_once __DIR__ . '/Cache.php';

/**
 * Library: Process
 *
 * process container to handle a list of tasks and their progress
 *
 * @package Piwik_PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_Process implements Iterator
{

    /**
     * basic step flags
     * @var int
     */
    const STEP_UNKNOWN =  0;
    const STEP_FAILED  = -1;


    /**
     * status-codes of a taks
     * @var int
     */
    const STATUS_FAILED       =-1;
    const STATUS_INIT         = 0;
    const STATUS_INPROGRESS   = 1;
    const STATUS_TRANSIENT    = 2;
    const STATUS_STEPFINISHED = 3;
    const STATUS_FINISHED     = 3;
    const STATUS_DEFERRED     = 4;
    const STATUS_SKIPED       = 5;

    /**
     * settings for the cache
     * @var string
     */
    const CACHE_ID_STATUS     = 'Autopluginlist_process';
    const CACHE_TTL = 3600;

    /**
     * error codes
     * @var int
     */
    const ERROR_CODE_UNKNOWN = 0;
    const ERROR_CODE_SUCCESS = 1;

    /**
     * max runtime, until we assume, that a previously started process is dead
     * @var int
     */
    const PROCESS_TIMEOUT = 120;

    /**
     * cache
     * @var PluginMarketplace_Cache
     */
    protected $cache = null;

    /**
     * current status of the install process and its tasks
     * @var array
     */
    protected $taskStatus = null;

    /**
     * Singleton
     * @var PluginMarketplace_Process
     */
    protected static $instance = null;


    /**
     * Iterator pointer
     * @var array
     */
    protected $currentTask = null;


    /**
     * flag to skip reload cache
     * @var boolean
     */
    protected $skipReloadCache = false;

    /**
     * Construtor
     * the firstcall is used for singleton
     * @param Piwik_CacheFile $cache - optional local cache
     */
    public function __construct(Piwik_CacheFile $cache = null)
    {
        if($cache === null) {
            $cache =  new PluginMarketplace_Cache('PluginMarketplace');
        }
        $this->cache = $cache;
        if (self::$instance === null) {
            self::$instance = $this;
        }
    }

    /**
     * Singleton Instance
     * @return PluginMarketplace_Process
     */
    static public function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * add a Task to the todolist
     * @param string $taskId
     * @param array $taskdef - task definititon/information
     * @return PluginMarketplace_Process
     */
    public function addTask($taskId, array $taskdef)
    {
        $this->loadStatus();
        $this->taskStatus['tasks'][$taskId] = array(
                'step' => self::STEP_UNKNOWN,
                'status' => self::STATUS_INIT,
                'info' => $taskdef,
                'history' => array(sprintf('%0.2f create',microtime(true)))
        );
        return $this->heartbeat(null, sprintf('add task %s', $taskId));
    }


    /**
     * mark the process as started
     * @throws RuntimeException - if the process is not runable
     * @return PluginMarketplace_Process
     */
    public function start()
    {
        if(!$this->isRunable())
        {
            throw new RuntimeException(Piwik_TranslateException('APUA_Exception_Process_stillrunning')); //.'<pre>'. print_r($this->getStatus(), true).'</pre>'
        }
        $this->taskStatus['process']['status'] = self::STATUS_TRANSIENT;
        $this->taskStatus['process']['sequence'] = 0;
        $this->taskStatus['process']['startts'] = time();
        $this->taskStatus['process']['endts'] = -1;
        return $this->heartbeat(null, 'start processor');
    }


    /**
     * mark the process as stopped
     * @param int $endstate
     * @param string $reason - text message, if process faild
     * @return PluginMarketplace_Process
     */
    public function stop($endstate = self::STATUS_FINISHED, $reason = null)
    {
        $this->loadStatus();
        $this->taskStatus['process']['status'] = $endstate;
        $this->taskStatus['process']['endts'] = time();
        $this->taskStatus['process']['reason'] = $reason?$reason:'';
        return $this->heartbeat(null,'stop processor');
    }


    /**
     * Mark a task in processing state
     * @param string $taskId  - Taskname
     * @param int     $stepId - current step, wich will be processed
     * @throws RuntimeException - if the task is unknown or the task was finished
     * @return PluginMarketplace_Process
     */
    public function markTaskprocessing($taskId, $stepId)
    {
        $this->loadStatus()->taskExists($taskId);
        if($this->taskStatus['tasks'][$taskId]['status']  == self::STATUS_FINISHED){
            throw new RuntimeException('Task was finnnished:'.$taskId);
        }
        $this->taskStatus['process']['sequence']++;
        $this->taskStatus['tasks'][$taskId]['step'] = $stepId;
        $this->taskStatus['tasks'][$taskId]['status'] = self::STATUS_TRANSIENT;
        return $this->heartbeat($taskId, 'TRANSIENT');
    }


    /**
     * mark the Task as failed
     * @param string $taskId
     * @param string $reason
     * @param string $nextStepId
     * @param int    $status
     * @return PluginMarketplace_Process
     */
    public function markTaskFailed($taskId, $reason, $error_code = -1, $nextStepId = self::STEP_UNKNOWN, $status = self::STATUS_FAILED)
    {
        $this->markTaskProcessed($taskId,$nextStepId, $status);
        $this->taskStatus['tasks'][$taskId]['exception'] = $reason;
        $this->taskStatus['tasks'][$taskId]['errorcode'] =  $error_code;
        return $this->heartbeat($taskId, 'exception: '.substr($reason,0,30));
    }

    /**
     * mark task as processed
     * @param string $taskId
     * @param string $nextStepId
     * @param int $status
     * @throws RuntimeException - if Task is unknown
     * @return PluginMarketplace_Process
     */
    public function markTaskProcessed($taskId, $nextStepId = self::STEP_UNKNOWN, $status = self::STATUS_STEPFINISHED)
    {
        $this->loadStatus()->taskExists($taskId);

        if($status == self::STATUS_STEPFINISHED &&  $nextStepId != self::STEP_UNKNOWN) {
            // set this step as finnished, and invoke next step
            return $this->heartbeat($taskId,'mark processed with next step')
            ->setTaskStep($taskId, $nextStepId);
        }
        $this->taskStatus['process']['sequence']++;
        $this->taskStatus['tasks'][$taskId]['status'] = $status;
        return $this->heartbeat($taskId, 'marked processed');
    }


    /**
     * Set the next Step of a Task
     * @param string $taskId
     * @param int    $nextStepId
     * @param int     $status - default self::STATUS_INPROGRESS
     * @throws RuntimeException - if the task is unknown
     * @return PluginMarketplace_Process
     */
    public function setTaskStep($taskId, $nextStepId, $status = self::STATUS_INPROGRESS)
    {
        $this->loadStatus()->taskExists($taskId);
        $this->taskStatus['process']['sequence']++;
        $this->taskStatus['tasks'][$taskId]['step'] = $nextStepId;
        $this->taskStatus['tasks'][$taskId]['status'] =  $status;
        return $this->heartbeat($taskId,'set step');
    }


    /**
     * add a history entry to the task
     * @param string $taskId
     * @param string $msg
     * @return PluginMarketplace_Process
     */
    public function addTaskHistory($taskId, $msg)
    {
        $this->loadStatus();
        if($taskId !== NULL ) {
            $this->taskExists($taskId);
        }
        return $this->heartbeat($taskId, 'manual Message: '.$msg);
    }


    /**
     * update an item of the taskinfo
     * @param string $taskId
     * @param string $varname
     * @param mixed $varvalue
     * @return PluginMarketplace_Process
     */
    public function setTaskAttribute($taskId, $varname, $varvalue)
    {
        $this->loadStatus()->taskExists($taskId);
        $this->taskStatus['tasks'][$taskId]['info'][$varname] = $varvalue;
        return $this->heartbeat($taskId, 'update taskinfo: "'.$varname .'" Val: ' . print_r($varvalue, true) );
    }


    /**
     * reset the process
     * @param boolean $doCheck - if true then throw exeption, if a process is in running state
     * @throws RuntimeException
     * @return PluginMarketplace_Process
     */
    public function reset($doCheck = false)
    {
        if($doCheck && $this->isRunning()){
            throw new RuntimeException('another process is still running'.print_r($this->taskStatus['process'],true));
        }
        $this->taskStatus = null;
        $this->currentTask = null;
        $this->skipReloadCache = false;
        return $this->saveStatus()->loadStatus();
    }


    /**
     * check, if a task is running
     * @return boolean
     */
    public function isRunning()
    {
        $this->loadStatus();
        if(!isset($this->taskStatus['process']['status'])){
            // that should never be, but unittested
            return false;
        }
        return ($this->taskStatus['process']['status'] === self::STATUS_TRANSIENT
                && $this->taskStatus['process']['heartbeat'] > 0
                && $this->taskStatus['process']['heartbeat'] + self::PROCESS_TIMEOUT > time());
    }


    /**
     * check if the process is runable
     * it it has tasks and the status of the process is ok
     * @return boolean
     */
    public function isRunable()
    {
        $isRunning = $this->isRunning();
        $this->skipReloadCache = true;
        return $this->hasTasks()  // must have tasks
        && ($this->taskStatus['process']['status'] === self::STATUS_INIT // proces is init or finished
                || $this->taskStatus['process']['status'] === self::STATUS_FINISHED
                // or process is running, but the heartbeart is too old
                || (!$isRunning && $this->taskStatus['process']['status'] === self::STATUS_TRANSIENT)
        );
    }


    /**
     * set an info item of the process
     * @param string $varname
     * @param mixed $value
     * @return PluginMarketplace_Process
     */
    public function setProcessInfo($varname,$value)
    {
        $this->loadStatus();
        $this->taskStatus['process'][$varname] =$value;
        return $this->heartbeat(null, sprintf('set Process info %s', $varname));
    }


    /**
     * check if the process has tasks
     * @return boolean
     */
    public function hasTasks()
    {
        $this->loadStatus();
        return !empty($this->taskStatus['tasks']);
    }


    /**
     * save heartbeat with a message to the heartbeat indicator
     * @param string $taskId
     * @param string $historyMsg
     * @return PluginMarketplace_Process
     */
    protected function heartbeat($taskId = null, $historyMsg = null)
    {
        empty($this->taskStatus) && $this->loadStatus();

        if($taskId !== null && isset($this->taskStatus['tasks'][$taskId])) {
            $step = $this->taskStatus['tasks'][$taskId]['step'];
            $status = $this->taskStatus['tasks'][$taskId]['status'];
            $this->taskStatus['tasks'][$taskId]['history'][]=sprintf('%0.3f Step: %d Status: %d -- %s',microtime(true), $step, $status, $historyMsg);
            $this->taskStatus['process']['history'][]=sprintf('%0.3f Task: %s Step: %d Status: %d -- %s',microtime(true),$taskId, $step, $status, $historyMsg);
        } else {
            $this->taskStatus['process']['history'][]=sprintf('%0.3f heartbeat: %s',microtime(true), $taskId == null?$historyMsg:'');
        }
        $this->taskStatus['process']['heartbeat'] = time();
        return $this->saveStatus();
    }


    /**
     * Load the current status of the process (and tasks) from cache
     * @return PluginMarketplace_Process
     */
    protected function loadStatus()
    {

        // skip (once) the load of the cace, for improvement of speed...
        if($this->skipReloadCache && !empty($this->taskStatus)) {
            $this->skipReloadCache = false;
            return $this;
        }

        $this->skipReloadCache = false;
        $this->taskStatus = $this->cache->get(self::CACHE_ID_STATUS);
        if(!empty($this->taskStatus)){
            if(empty($this->taskStatus['tasks'])){
                // reset arrayIterator
                $this->currentTask= null;
            }
            return $this;
        }
        // create default array to represent the current status
        $this->taskStatus=array(
                'process' => array(
                        'startts' => -1,
                        'heartbeat' => -20,
                        'endts' => -1,
                        'sequence' => 0,
                        'status' => self::STATUS_INIT,
                        'history' => array()),
                'tasks' => array()
        );
        $this->currentTask = null;
        return $this;
    }


    /**
     * save the current status to the cache
     * @return PluginMarketplace_Process
     */
    protected function saveStatus()
    {
        $this->cache->set(self::CACHE_ID_STATUS, $this->taskStatus);
        return $this;
    }


    /**
     * reinsert tasks, that were marked as deffered
     * @return PluginMarketplace_Process
     */
    public function reinitDefferedTasks()
    {
        $this->loadStatus();
        foreach ($this->taskStatus['tasks'] as $taskId => $info) {
            // Continue Installation
            if($info['status'] == self::STATUS_DEFERRED){
                $this->setTaskStep($taskId, $info['step'], self::STATUS_INIT);
            }
        }
        return $this;
    }


    /**
     * Get a attribute of a Task
     * @param string $taskId
     * @param string $itemidx
     * @return mixed - if $itemidx == null then return the whole status of the selected task, otherwise return the info-attribute of the task
     */
    public function getTaskAttribute($taskId, $itemidx = null)
    {
        $this->loadStatus()->taskExists($taskId);
        return $itemidx == null
        ? $this->taskStatus['tasks'][$taskId]
        : $this->is($this->taskStatus['tasks'][$taskId]['info'][$itemidx], null);
    }


    /**
     * get the status of a task
     * @param unknown $taskId
     * @return int
     */
    public function getTaskStatus($taskId)
    {
        $this->loadStatus()->taskExists($taskId);
        return $this->taskStatus['tasks'][$taskId]['status'];
    }


    /**
     * get current step of the task
     * @param unknown $taskId
     * @return int
     */
    public function getTaskStep($taskId)
    {
        $this->loadStatus()->taskExists($taskId);
        return $this->taskStatus['tasks'][$taskId]['step'];
    }

    /**
     * get Process Attribute
     * @param unknown $itemidx
     * @return mixed if $itemidx == null then return the whole status-array of the process, otherwise return the info-attribute of the process
     */
    public function getProcessAttribute( $itemidx = null )
    {
        $this->loadStatus();
        return $itemidx == null
        ? $this->taskStatus['process']
        : $this->is($this->taskStatus['process'][$itemidx]);
    }


    /**
     * Load current status of the installprocess from cache
     * @param string|null $pluginName
     * @return mixed
     */
    public function getStatus($taskId = null, $taskInfo = null)
    {
        $this->loadStatus();
        $this->skipReloadCache = true;
        $this->taskStatus['isRunning'] = $this->isRunning();
        $this->skipReloadCache = true;
        $this->taskStatus['isRunable'] = $this->isRunable();
        $this->taskStatus['now'] = microtime(true);

        // return whole status
        if($taskId === null){
            return $this->taskStatus;
        }

        // return process Info:
        if($taskId == 'process') {
            return $taskInfo == null ?$this->taskStatus['process']: $this->is($this->taskStatus['process'][$taskInfo],null);
        }


        // return task status
        if(isset($this->taskStatus['tasks'][$taskId])) {
            return $taskInfo == null ?$this->taskStatus['tasks'][$taskId]['status'] : $this->is($this->taskStatus['tasks'][$taskId]['info'][$taskInfo],null);
        }
        // special mapping
        switch($taskId) {
            case 'tasks':
                return $this->taskStatus['tasks'];
            default:
                return null;
        }
    }

    /**
     * Check if a task exists in the tasklist
      
     * @param string $taskId
     * @throws RuntimeException - if the task is not available
     * @return PluginMarketplace_Process
     */
    public function taskExists($taskId)
    {
        empty($this->taskStatus) && $this->loadStatus();
        if(!isset($this->taskStatus['tasks'][$taskId])) {
            throw new RuntimeException('Task is unknown:'.$taskId);
        }
        return $this;
    }

     
    /**
     * convenience method to check if given value is set. if so, value is return, otherwise the default
     * @param mixed $arg value to check
     * @param mixed $default value returned if $arg is unset
     * @return mixed return value of $arg or $default if $arg is unset
     */
    function is( &$arg, $default = null)
    {
        if (isset($arg)) {
            return $arg;
        }
        return $default;
    }


    /*
     * foreach iterator function
    * traverse foreach $process
    */
    /**
     * (non-PHPdoc)
     * @see Iterator::rewind()
     */
    public function rewind()
    {
        // find first "runable task"
        $this->next();
    }
    /**
     * (non-PHPdoc)
     * @see Iterator::current()
     */
    public function current()
    {
        if($this->currentTask === null){
            return null;
        }
        return $this->taskStatus['tasks'][$this->currentTask];
    }

    /**
     * (non-PHPdoc)
     * @see Iterator::key()
     */
    public function key()
    {
        return $this->currentTask;
    }


    /**
     * (non-PHPdoc)
     * @see Iterator::next()
     */
    public function next()
    {
        if(!$this->isRunning()) {
            $this->currentTask = null;
            return;
        }
        foreach ($this->taskStatus['tasks'] as $taskId => $taskdef) {
            if($taskdef['status'] == self::STATUS_INIT
                    || $taskdef['status'] == self::STATUS_INPROGRESS) {
                $this->currentTask = $taskId;
                return;
            }
        }
        $this->currentTask = null;
    }

    /**
     * (non-PHPdoc)
     * @see Iterator::valid()
     */
    public function valid()
    {
        return  ($this->currentTask !== null && isset($this->taskStatus['tasks'][$this->currentTask]));
    }


}