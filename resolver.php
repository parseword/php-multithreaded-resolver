<?php
/*
 * Copyright 2017 Shaun Cummiskey, <shaun@shaunc.com> <http://shaunc.com>
 * <https://github.com/ampersign/php-multithreaded-resolver>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and 
 * limitations under the License.
 */

error_reporting(E_ALL);
define('DEBUG', true);
define('MAX_THREADS', 16);
define('PTHREADS_VERSION', preg_replace('|[^0-9\.]|', '', phpversion('pthreads')));

//We need pthreads in order to proceed
if (!PTHREADS_VERSION) {
    die("This script requires PHP compiled with the pthreads extension.\n");
}

function debug($message) {
    echo DEBUG ? sprintf("%17.6f", microtime(true)) . ':: ' . $message . "\n" : '';
}

//A class for the worker threads
class ResolverThread extends Thread {
    
    private $id = null;
    protected $host = null;
    
    public function __construct($id, $host) {
        $this->id = $id;
        $this->host = $host;
    }
    
    public function run() {

        if (preg_match('|[a-z]|i', $this->host)) {
            //We got a hostname, resolve to an IP
            if ($result = @dns_get_record($this->host, DNS_A)) {
                echo $this->host . ':' . $result[0]['ip'] . "\n";
                //echo sprintf("%17.6f", microtime(true)) . ':: ' . "{$this->id}:{$this->host}:" . $in_addr . ' ::: ' . $result[0]['ip'] . "\n";
            }
            else {
                echo $this->host . ":SERVFAIL\n";
                //echo sprintf("%17.6f", microtime(true)) . ':: ' . "{$this->id}:{$this->host}:" . $in_addr . " ::: SERVFAIL\n";
            }
        }
        
        else {
            //Construct in-addr.arpa address and resolve to PTR
            $quads = explode('.', $this->host);
            $in_addr = $quads[3] . '.' . $quads[2] . '.' . $quads[1] . '.' . $quads[0] . '.in-addr.arpa';
            if ($result = @dns_get_record($in_addr, DNS_PTR)) {
                echo $this->host . ':' . $result[0]['target'] . "\n";
                //echo sprintf("%17.6f", microtime(true)) . ':: ' . "{$this->id}:{$this->host}:" . $in_addr . ' ::: ' . $result[0]['target'] . "\n";
            }
            else {
                echo $this->host . ":SERVFAIL\n";
                //echo sprintf("%17.6f", microtime(true)) . ':: ' . "{$this->id}:{$this->host}:" . $in_addr . " ::: SERVFAIL\n";
            }
        }
        usleep(100);
    }
    public function getId() {
        return $this->id;
    }
}

$hosts = array_map('trim', file('hosts.txt'));
$totalJobs = count($hosts);
$jobsStarted = 0;
$workers = array();
$timeStart = microtime(true);

//Load the workers queue with MAX_THREADS threads
while (@$i++ < MAX_THREADS) {
    $threadId = bin2hex(openssl_random_pseudo_bytes(8));
    $workers[$threadId] = new ResolverThread($threadId, array_pop($hosts));
    $workers[$threadId]->start();
    $jobsStarted++;
}

//Manage the thread queue until there's no work left to do
while (1) {
    usleep(100000);
    $deadThreads = 0;
    foreach ($workers as $thread) {

        //If the thread is still working, skip it
        if ($thread->isRunning()) {
            continue;
        }
        
        //If the thread is completed, join and destroy it
        debug('Calling join() on ' . $thread->getId());
        if ($thread->join()) {
            unset($workers[$thread->getId()]);
            $deadThreads++;
        }
    }
    
    //If no threads were finished, go wait again
    if ($deadThreads == 0)
        continue;
    
    //Refill the thread queue
    $threadsToStart = (count($hosts) > $deadThreads) ? $deadThreads : count($hosts);
    debug("Starting $threadsToStart threads");
    for ($i=0; $i < $threadsToStart; $i++) {
        $threadId = bin2hex(openssl_random_pseudo_bytes(8));
        debug('memory_get_usage ' . memory_get_usage());
        debug('workers[] ' . count($workers) . ' deadThreads ' . $deadThreads . ' threadsToStart ' . $threadsToStart . " Creating new thread $threadId");
        $workers[$threadId] = new ResolverThread($threadId, array_pop($hosts));
        $workers[$threadId]->start();
        $jobsStarted++;
    }
    
    //Bail when we're out of work to do
    if ($jobsStarted >= $totalJobs && count($workers) == 0) {
        break;
    }
}

echo '#Script took ' . sprintf('%.02f', microtime(true) - $timeStart) . " seconds to execute\n";
