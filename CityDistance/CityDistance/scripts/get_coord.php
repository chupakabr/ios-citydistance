<?php
//  get_coord.php
//  CityDistance
//
//  Created by Valeriy Chevtaev on 5/7/12.
//  Copyright (c) 2012 myltik@gmail.com. All rights reserved.
//
//
// curl -X GET 'http://maps.googleapis.com/maps/api/geocode/json?address=Velikie+Luki,+Russia&sensor=false'
//


require_once "common.php";


// Run app
exit ( cd_runner ("GET_COORD", "do_main") );


//
// The app

function do_main()
{
    $inFile = "out/cities_err.txt";
    $outFile = "out/coords.txt";
    $outErrFile = "out/err_adrr.txt";
    
    $ggUrl = "http://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=";
    $result = array();
    $errorAddrs = array();
    
    // Read addresses
    $addrList = explode("\n", file_get_contents($inFile));
//    $addrList = array("Lagos,+Nigeria");
    
    // Grab data
    $i = 0;
    $succ = 0;
    foreach ($addrList as $addr)
    {
        if (empty($addr)) {
            continue;
        }
        
        $i = $i+1;
        $fullUrl = $ggUrl . $addr;
        
        cd_echo ("* [$i] Getting coords for URL=$fullUrl");
        
        $output = file_get_contents($fullUrl);
        if (empty($output)) {
            cd_echo ("  [$i] ERROR: Cannot get coords for URL=$fullUrl");
            $errorAddrs[] = $addr;
        }
        else {
            $outputJson = json_decode($output, true);
            if (empty($outputJson)) {
                cd_echo ("  [$i] ERROR: Cannot parse JSON from response (URL=$fullUrl)");
                $errorAddrs[] = $addr;
            }
            else {
                if ($outputJson["status"] != "OK") {
                    cd_echo ("  [$i] ERROR: Status is not OK for URL=$fullUrl");
                    $errorAddrs[] = $addr;
                }
                else {
                    $coords = array(
                        "lat" => $outputJson["results"][0]["geometry"]["location"]["lat"],
                        "lng" => $outputJson["results"][0]["geometry"]["location"]["lng"]
                    );
                    $result[$addr] = $coords;
                    
                    cd_echo ("  [$i] OK: lat=" . $coords["lat"] . ", lng=" . $coords["lng"]);
                    
                    $succ = $succ+1;
                }
            }
        }
    }
    
    cd_echo ("Processed cities $succ, " . ($i - $succ) . " errors found ");
    
    // Save results
    cd_echo (" ");
    cd_echo ("--");
    cd_echo (" ");
//    file_put_contents($outFile, "");
    foreach ($result as $city => $coords)
    {
        $lat = $coords["lat"];
        $lng = $coords["lng"];
        file_put_contents($outFile, "$city $lat $lng\n", FILE_APPEND);
    }
    
    // Write error cities
    file_put_contents($outErrFile, implode("\n", $errorAddrs));
}

