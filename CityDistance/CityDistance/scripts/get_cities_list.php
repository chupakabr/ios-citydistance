<?php
//  get_cities_list.php
//  CityDistance
//
//  Created by Valeriy Chevtaev on 5/7/12.
//  Copyright (c) 2012 myltik@gmail.com. All rights reserved.

require_once "common.php";


// Run app
exit ( cd_runner ("GET_CITIES_LIST", "do_main") );


//
// The app
    
function do_main()
{
    // Config
    $outFile = "out/cities.txt";
    $urlParsers = array(
        "http://www.citymayors.com/statistics/largest-cities-mayors-1.html" => "cd_parser_citymayors_com",
        "http://www.citymayors.com/statistics/largest-cities-mayors-151.html" => "cd_parser_citymayors_com",
        "http://www.citymayors.com/statistics/largest-cities-mayors-301.html" => "cd_parser_citymayors_com",
        "http://www.citymayors.com/statistics/largest-cities-mayors-451.html" => "cd_parser_citymayors_com",
        "http://en.wikipedia.org/wiki/List_of_cities_and_towns_in_Russia_by_population" => "cd_parser_wikipedia_org_rus",
        "http://en.wikipedia.org/wiki/List_of_United_States_cities_by_population" => "cd_parser_wikipedia_org_usa",
        "http://en.wikipedia.org/wiki/List_of_countries_and_capitals_in_native_languages" => "cd_parser_wikipedia_org_capitals"
    );
    
    // Run all parsers
    $result = array();
    foreach ($urlParsers as $url => $parser) {
        $subRes = call_user_func($parser, $url);
        $result = array_merge($result, $subRes);
    }
    
    $result = array_unique($result);
    
    // Result output
    if (false) {
        foreach ($result as $city) {
            cd_echo ("$city");
        }
    }
    
    // Write output to file
    file_put_contents($outFile, implode("\n", $result));
}

function cd_parser_citymayors_com($url)
{
    $res = array();
    
    cd_echo ("cd_parser_citymayors_com($url) - start");
    
//    <body>
//    <table border="0" cellpadding="0" cellspacing="2" width="750"> <!-- 0 -->
//    <tr> <!-- 0 -->
//    <td width="440" valign="top" align="left"> <!-- 2 -->
//    <font size="2"> <!-- 8 -->
//    <table width="430" border="1" cellspacing="2" cellpadding="0"> <!-- 0 -->
//    <tr> <!-- 1, as the first is header -->
//      <td width="30">
//          <div align="center">
//              <font size="-2" face="Arial">458</font>
//            </div>
//      </td>
//      <td bgcolor="#ccccff" width="72">
//          <font size="-2" face="Arial"><b>Mombasa</b></font>
//      </td>
//      <td width="72">
//          <font size="-2" face="Arial">Kenia</font>
//      </td>
//      <td bgcolor="#ccccff" width="60">
//    ...
    $htmlPage = file_get_contents($url);
    if (empty($htmlPage)) {
        cd_echo ("WARN Empty page returned for URL=$url");
    }
    else {
        // Parse
        $doc = new DOMDocument();
        $doc->strictErrorChecking = FALSE;
        $doc->loadHTML($htmlPage);
        $html = simplexml_import_dom($doc);
        
        foreach ($html->body->table[0]->tr[0]->td[2]->font[8]->table[0]->tr as $tr)
        {
            $city = ucwords(strtolower($tr->td[1]->font[0]->b[0]));
            if (empty($city)) {
                $city = ucwords(strtolower($tr->td[1]->font[0]));
            }
            if (empty($city)) {
                $city = ucwords(strtolower($tr->td[1]->div[0]->font[0]));
            }
            
            $country = ucwords(strtolower($tr->td[2]->font[0]));
            
            if (!empty($city) && !empty($country)) {
                $res[] = urlencode("$city") . "," . urlencode(" $country");
//                echo "* " . urlencode("$city") . "," . urlencode(" $country") . "\n";
            }
        }
    }
    
    cd_echo ("cd_parser_citymayors_com - finish, found " . count($res) . " entries");
    
    return $res;
}
   
function cd_parser_wikipedia_org_rus($url)
{
    $res = array();
    
    cd_echo ("cd_parser_wikipedia_org_rus($url) - start");
    
//      <body>
//      <div> <!-- 2 -->
//      <div> <!-- 2 -->
//      <div> <!-- 3 -->
//      <table> <!-- 0 -->
//        <tr>
//          <td>002</td>
//          <td>002</td>
//          <td>
//              <b>
//                  <a href="/wiki/Saint_Petersburg" title="Saint Petersburg">Saint Petersburg</a>
//              </b>
//          </td>
//    ...
    $htmlPage = file_get_contents($url);
    if (empty($htmlPage)) {
        cd_echo ("WARN Empty page returned for URL=$url");
    }
    else {
        // Parse
        $doc = new DOMDocument();
        $doc->strictErrorChecking = FALSE;
        $doc->loadHTML($htmlPage);
        $html = simplexml_import_dom($doc);
        
        foreach ($html->body->div[2]->div[2]->div[3]->table[0]->tr as $tr)
        {
            $city = ucwords(strtolower($tr->td[2]->b[0]->a[0]));
            if (empty($city)) {
                $city = ucwords(strtolower($tr->td[2]->a[0]));
            }
            
            $country = "Russia";
            
            if (!empty($city) && !empty($country)) {
                $res[] = urlencode("$city") . "," . urlencode(" $country");
//                echo "* " . urlencode("$city") . "," . urlencode(" $country") . "\n";
            }
        }
    }
        
    cd_echo ("cd_parser_wikipedia_org_rus - finish, found " . count($res) . " entries");
    
    return $res;
}

function cd_parser_wikipedia_org_usa($url)
{
    $res = array();
    
    cd_echo ("cd_parser_wikipedia_org_usa($url) - start");
    
//      <body>
//      <div> <!-- 2 -->
//      <div> <!-- 2 -->
//      <div> <!-- 3 -->
//      <table> <!-- 2 -->
//        <tr>
//          <td>002</td>
//          <td>
//              <b>
//                  <a href="/wiki/Seattle" title="Seattle">Seattle</a>
//              </b>
//          </td>
//    ...
    $htmlPage = file_get_contents($url);
    if (empty($htmlPage)) {
        cd_echo ("WARN Empty page returned for URL=$url");
    }
    else {
        // Parse
        $doc = new DOMDocument();
        $doc->strictErrorChecking = FALSE;
        $doc->loadHTML($htmlPage);
        $html = simplexml_import_dom($doc);
        
        foreach ($html->body->div[2]->div[2]->div[3]->table[2]->tr as $tr)
        {
            $city = ucwords(strtolower($tr->td[1]->b[0]->a[0]));
            if (empty($city)) {
                $city = ucwords(strtolower($tr->td[1]->a[0]));
            }
            
            $country = "USA";
            
            if (!empty($city) && !empty($country)) {
                $res[] = urlencode("$city") . "," . urlencode(" $country");
//                echo "* " . urlencode("$city") . "," . urlencode(" $country") . "\n";
            }
        }
    }
    
    cd_echo ("cd_parser_wikipedia_org_usa - finish, found " . count($res) . " entries");
    
    return $res;
}

function cd_parser_wikipedia_org_capitals($url)
{
    $res = array();
    
    cd_echo ("cd_parser_wikipedia_org_capitals($url) - start");
    
//      <body>
//      <div> <!-- 2 -->
//      <div> <!-- 2 -->
//      <div> <!-- 3 -->
//      <table class="wikitable"> <!-- from 1 to 23 -->
//        <tr>
//          <td>
//              <center><a href="">Estonia</a></center>
//          </td>
//          <td>
//              <center><a href="">Tallin</a></center>
//          </td>
//    ...
    $htmlPage = file_get_contents($url);
    if (empty($htmlPage)) {
        cd_echo ("WARN Empty page returned for URL=$url");
    }
    else {
        // Parse
        $doc = new DOMDocument();
        $doc->strictErrorChecking = FALSE;
        $doc->loadHTML($htmlPage);
        $html = simplexml_import_dom($doc);
        
        foreach ($html->body->div[2]->div[2]->div[3]->table as $table)
        {
            foreach ($table->tr as $tr)
            {
                $city = ucwords(strtolower($tr->td[1]->center[0]->a[0]));
                if (empty($city)) {
                    $city = ucwords(strtolower($tr->td[1]->a[0]));
                }
                
                $country = ucwords(strtolower($tr->td[0]->center[0]->a[0]));
                if (empty($country)) {
                    $country = ucwords(strtolower($tr->td[0]->a[0]));
                }
                
                if (!empty($city) && !empty($country)) {
                    $res[] = urlencode("$city") . "," . urlencode(" $country");
//                    echo "* " . urlencode("$city") . "," . urlencode(" $country") . "\n";
                }
            }
        }
    }
    
    cd_echo ("cd_parser_wikipedia_org_capitals - finish, found " . count($res) . " entries");
    
    return $res;
}
