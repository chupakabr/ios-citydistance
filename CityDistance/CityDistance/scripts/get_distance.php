<?php
//  get_distance.php
//  CityDistance
//
//  Created by Valeriy Chevtaev on 5/7/12.
//  Copyright (c) 2012 myltik@gmail.com. All rights reserved.
//
//
// http://maps.googleapis.com/maps/api/directions/json?origin=Velikie+Luki&destination=Riga&sensor=false
//

require_once "common.php";


// Error codes
define('CD_OK', 0);
define('CD_ERROR', 1);
define('CD_CHANGE_PROXY', 2);
define('CD_REQUEST_DENIED', 3);


// Run app
exit ( cd_runner ("GET_DISTANCE", "do_main") );


//
// The app
    

class CD_Proxy
{
    private static $instance_;
    
    private $proxies_;
    private $cur_;
    
    private function __construct() {
        $proxyFile = "proxy.txt";
        $this->proxies_ = explode("\n", file_get_contents($proxyFile));
        array_unshift($this->proxies_, "DIRECT");
        
        $this->cur_ = null;
    }
    
    public static function instance() {
        if (is_null(self::$instance_)) {
            self::$instance_ = new CD_Proxy();
        }
        
        return self::$instance_;
    }
    
    public function renew() {
        $cur = $this->cur_;
        
        while (count($this->proxies_) > 0)
        {
            $next = array_shift($this->proxies_);
            if (!empty($next)) {
                cd_echo ("[PROXY] Changing from '$cur' to '$next'");
                $this->cur_ = $next;
                return true;                
            }
        }
        
        cd_echo ("[PROXY] ERROR: No proxies left");
        return false;
    }
    
    public function ctx() {
        if ($this->cur_ == "DIRECT") {
            return null;
        }
        
        $arr = array(
            'http' => array(
                'proxy' => 'tcp://' . $this->cur_,
                'request_fulluri' => true
            )
        );
        return stream_context_create($arr);
    }
}


// Route object
class CD_Route
{
    // Props
    private $origin_;
    private $dest_;
    private $distance_;
    private $duration_;
    
    // Caches
    private $route_str_;
    private $route_str_len_;
    
    
    public function __construct($origin, $dest, $distance = 0, $duration = 0) {
        $this->origin_ = $origin;
        $this->dest_ = $dest;
        $this->distance_ = $distance;
        $this->duration_ = $duration;
    }
    
    public function getOrigin() {
        return $this->origin_;
    }
    
    public function getDest() {
        return $this->dest_;
    }
    
    public function getDistance() {
        return $this->distance_;
    }
    
    public function getDuration() {
        return $this->duration_;
    }
    
    public function fetchInfo($baseUrl) {
        
        // GG URL for the route
        $url = $baseUrl . "origin=" . $this->getOrigin() . "&destination=" . $this->getDest();
        cd_echo ("  * URL = '$url'");
        
        // Query GG
        $ctx = CD_Proxy::instance()->ctx();
        $output = file_get_contents($url, false, $ctx);
        
        if (empty($output)) {
            cd_echo ("  * ERROR: Cannot get distance for URL = '$url'");
            return CD_ERROR;
        }
        else {
            $outputJson = json_decode($output, true);
            if (empty($outputJson)) {
                cd_echo ("  * ERROR: Cannot parse JSON from response (URL = '$url')");
                return CD_ERROR;
            }
            else {
                $st = $outputJson["status"];
                
                if ($st == "OK") {
                    // Driving route between points found
                    $distance = $outputJson["routes"][0]["legs"][0]["distance"]["value"];
                    $duration = $outputJson["routes"][0]["legs"][0]["duration"]["value"];
                    
                    cd_echo ("  * OK: distance=$distance, duration=$duration");
                    $this->distance_ = $distance;
                    $this->duration_ = $duration;
                    
                    return CD_OK;
                }
                else if ($st == "ZERO_RESULTS" || $st == "NOT_FOUND") {
                    // No driving route between points
                    cd_echo ("  * OK (ZERO_RESULTS): distance=0, duration=0");
                    $this->distance_ = 0;
                    $this->duration_ = 0;
                    
                    return CD_OK;                    
                }
                else {
                    // ERROR
                    cd_echo ("  * ERROR: Status '$st' is not OK for URL = '$url'.");
                    
                    if ($st == 'OVER_QUERY_LIMIT') {
                        cd_echo ("  * ERROR: OVER_QUERY_LIMIT (Proxy change needed)");
                        return CD_CHANGE_PROXY;
                    }
                    else if ($st == 'REQUEST_DENIED') {
                        cd_echo ("  * ERROR: REQUEST_DENIED");
                        return CD_REQUEST_DENIED;
                    }
                    
                    return CD_ERROR;
                }
            }
        }
        
        // WTF
        cd_echo (" * ERROR: !!! Cannot be here !!!");
        return CD_ERROR;
    }
    
    public function equals($route2) {
        return $route2->hashCode() == $this->hashCode();
    }
    
    public function routeString($delim = " - ") {
//        if (empty($this->route_str_)) {
            if (strcmp($this->getDest(), $this->getOrigin()) < 0) {
                $this->route_str_ = $this->getDest() . $delim . $this->getOrigin();
            }
            else {
                $this->route_str_ = $this->getOrigin() . $delim . $this->getDest();
            }
//        }
        
        return $this->route_str_;
    }
    
    public function routeStringLen() {
        if (!$this->route_str_len_) {
            $this->route_str_len_ = strlen($this->routeString());
        }
        
        return $this->route_str_len_;
    }
    
    public function hashCode() {
        
        if (true) {
            return md5($this->routeString());
        }
        else {
            
            $hash = 0;
            $str = $this->routeString();
            $strLength = $this->routeStringLen();
            
            for ($i = 0; $i < $strLength; $i++) {
    //            $hash = ((int) (31 * $hash + ord($str[$i])));
                $hash = ( (int) (($hash<<5) - $hash) + ord($str[$i]) );
                $hash = $hash & $hash; // Convert to 32bit integer
    //            echo "$i: $hash, 0+str[$i]=" . (0+ord($str[$i])) . "\n";
            }
            
    //        echo "STR($str) STRLEN($strLength) HASH($hash)\n";
            
            return $hash;
        }
    }
}

// Route List container
class CD_RouteList
{
    private $routes_;
    
    public function __construct() {
        $this->routes_ = array();
    }
    
    public function contains($r2) {
        return array_key_exists($r2->hashCode(), $this->routes_);
    }
    
    public function add($route) {
        $this->routes_[$route->hashCode()] = $route;
        return $this;
    }
    
    public function get($r) {
        return $this->routes_[$r->hashCode()];
    }
    
    public function getAll() {
        return $this->routes_;
    }
    
    public function count() {
        return count($this->routes_);
    }
}


// Business
function do_main()
{
    $fetchAttempts = 5;
    $maxErrorsInRow = 200;
    $inFile = "out/cities.txt";
    $outFile = "out/distance.txt";
    
    $ggUrl = "http://maps.googleapis.com/maps/api/directions/json?sensor=false&mode=driving&units=metric&";   // +"origin" +"destination"
    $result = new CD_RouteList();
    
    // Read addresses
    $originList = explode("\n", file_get_contents($inFile));
    $destList = explode("\n", file_get_contents($inFile));
//    $originList = array("Riga,+Latvia", "Velikie+Luki,+Russia", "St+Petersburg,+Russia", "Seattle,+USA");
//    $destList = $originList;    // clone
    
    // Read existing data
    $inCSV = explode("\n", file_get_contents($outFile));
    if ($inCSV != FALSE)
    {
        foreach ($inCSV as $line)
        {
            if (empty($line)) {
                continue;
            }
            
            $routeArray = explode(" ", $line);
            if (count($routeArray) != 4) {
                cd_echo ("[IN FILE] ERROR Parse: Invalid file format, string '$line'");
                continue;
            }
            
            $route = new CD_Route($routeArray[0], $routeArray[1], $routeArray[2], $routeArray[3]);
            if ($result->contains($route)) {
                cd_echo ("[IN FILE] Route has been added recently, route = '$line'");
            }
            else {
		cd_echo ("[IN FILE] Addring existing route = '$line'");
                $result->add($route);
            }
        }
    }
    else {
        cd_echo ("[IN FILE] No existing routes found in outFile = '$outFile'");
    }
    
    // Do
    $i = 0;
    $k = 0;
    $ern = 0;
    $unprocessedCities = array();
    $isRunning = CD_Proxy::instance()->renew();
    while ($isRunning)
    {
        foreach ($originList as $originCity)
        {
            if (!$isRunning) {
                break;
            }
            
            if (empty($originCity)) {
                continue;
            }
            
            foreach ($destList as $destCity)
            {
                if (!$isRunning) {
                    break;
                }
                
                if (empty($destCity)) {
                    continue;
                }
                
                if ($destCity == $originCity) {
                    continue;
                }
                
                $i++;   // iteration
                
                $route = new CD_Route($originCity, $destCity);
                cd_echo ("Trying route ($k-$i, $ern): " . $route->routeString());
                
                if ($result->contains($route)) {
                    // Route already exists
                    $existingRoute = $result->get($route);
                    cd_echo ("  * Route already exists with distance=" . $existingRoute->getDistance() . " and duration=" . $existingRoute->getDuration());
                    continue;
                }
                
                // Fetch route
                $attempt = 0;
                $found = false;
                while ($attempt < $fetchAttempts) {
                    
                    $attempt++;
                    
                    $code = $route->fetchInfo($ggUrl);
                    
                    // Success
                    if ($code == CD_OK) {
                        // Add to results
                        $result->add($route);
                        $found = true;
                        $ern = 0;
                        break;
                    }
                    
                    
                    //
                    // Process errors
                    
                    else if ($code == CD_CHANGE_PROXY || $ern >= $maxErrorsInRow) {
                        
                        // Reset errn count
                        if ($ern >= $maxErrorsInRow) {
                            $ern = 0;
                        }
                        
                        // Renew proxy
                        if (!CD_Proxy::instance()->renew()) {
                            $isRunning = false;
                            break;
                        }
                    }
                    else if ($code == CD_REQUEST_DENIED) {
                        // Just retry
                    }
                    else {  //CD_ERROR
                        // Just retry
                    }
                    
                    // Log attempt
                    $ern++;
                    cd_echo ("  * Attempt $attempt for Route=" . $route->routeString());
                }
                
                // Add to unprocessed pairs
                if (!$found) {
                    if (!in_array($originCity, $unprocessedCities)) {
                        $unprocessedCities[] = $originCity;
                    }
                    if (!in_array($destCity, $unprocessedCities)) {
                        $unprocessedCities[] = $destCity;
                    }
                }
                
            }   // foreach dest
            
        }   // foreach origins
        
        // Check whether all Routes are fetched
        if (count($unprocessedCities) > 0) {
            cd_echo (" ");
            cd_echo ("NOTICE: UNPROCESSED CITIES COUNT IS " . count($unprocessedCities));
            cd_echo (" ");
            
            $originList = $unprocessedCities;
            $destList = $unprocessedCities;
            $k++;
        }
        else {
            $isRunning = false;
        }
        
    }   // while isRunning
    
    // Save results
    cd_echo (" ");
    cd_echo ("--");
    cd_echo (" ");
    cd_echo ("Processed routes number is " . $result->count());
    cd_echo (" ");
    file_put_contents($outFile, "");
    $res = $result->getAll();
    foreach ($res as $hashCode => $route)
    {
        $routeStr = $route->routeString(" ");
        $distance = $route->getDistance();
        $duration = $route->getDuration();
        cd_echo ("$routeStr : $distance $duration");
        file_put_contents($outFile, "$routeStr $distance $duration\n", FILE_APPEND);
    }
}



//
// AUX
function process_coord_to_cities()
{
    $outFile = "out/cities_nice.txt";
    $inFile = "out/coords.txt";
    
    $addrList = explode("\n", file_get_contents($inFile));
    file_put_contents($outFile, "");
    foreach ($addrList as $addr)
    {
        $csv = explode(" ", $addr);
        $city = $csv[0];
        file_put_contents($outFile, "$city\n", FILE_APPEND);
    }
}
