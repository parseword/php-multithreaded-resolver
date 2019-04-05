# php-multithreaded-resolver
A multithreaded DNS resolver in PHP using basic pthreads capability. You supply 
a list of IP addresses, hostnames, or both, and the script will resolve each 
one to its DNS counterpart.

I often work with large batches of hosts that need to be translated from IP to 
hostname or vice versa. If you only have a handful of hosts to deal with, a 
simple script like the following will suffice:

```php
<?php
$ips = array_map('trim', file('ips.txt'));
foreach ($ips as $ip) {
    echo $ip . ':' . gethostbyaddr($ip) . "\n";
}
```

When you're processing many thousands of hosts at a time, such a script 
can take hours to complete. I needed a multithreaded solution, so here it is. 
Using a pool of 16 threads, this script is capable of performing 20,000 
lookups in 5 minutes. Your mileage will vary based on your hardware, DNS 
server cache, network connectivity, the hosts being resolved and their 
DNS servers...

## Requirements

You must have a version of PHP with the 
[pthreads](https://github.com/krakjoe/pthreads) extension installed. You 
can compile it into PHP, get it from PECL, or perhaps install it through your 
OS package manager. This script was tested and is known to work on:

* Linux, PHP 5.6.30 with pthreads 2.0.11
* Linux, PHP 7.1.x with pthreads 3.1.7dev 
* Linux, PHP 7.2.x with pthreads 3.2.1dev 
* Windows, PHP 5.4.45 with pthreads 2.0.9
* Windows, PHP 5.6.30 with pthreads 2.0.9

## A note on DNS servers

This script will use whichever DNS server your operating system is configured 
for. Many public DNS servers, including those operated by Google (8.8.8.8), 
Level3 (4.2.2.4), etc. employ a throttling mechanism that refuses or ignores your 
queries if you send too many too quickly. When resolving hosts in bulk, 
you're better off using your ISP's DNS server or running a local recursive 
DNS server.

As an aside, if you do operate your own DNS server(s), running this script 
against a portion of the [Alexa Top 1M list](http://s3.amazonaws.com/alexa-static/top-1m.csv.zip) is 
a good way to prime a freshly rebooted resolver.

