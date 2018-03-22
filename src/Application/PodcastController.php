<?php

/*
 * PHP script for downloading videos from youtube
 * Copyright (C) 2012-2018  John Eckman
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, see <http://www.gnu.org/licenses/>.
 */

namespace YoutubeDownloader\Application;

use Exception;
use YoutubeDownloader\Config;
use YoutubeDownloader\VideoInfo\VideoInfo;

/**
 * The download controller
 */
class PodcastController extends ControllerAbstract
{
    /**
     * Excute the Controller
     *
     * @param string                            $route
     * @param YoutubeDownloader\Application\App $app
     */
    public function execute()
    {
        $config = $this->get('config');
        $toolkit = $this->get('toolkit');
        $youtube_provider = $this->get('YoutubeDownloader\Provider\Youtube\Provider');
        
        $actual_link = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $site = substr($actual_link, 0, strrpos($actual_link, '/')+1);
        
        
            
        $dom=new \DOMDocument();
        if (isset($_GET['channelid'])) {
            $dom->load('https://www.youtube.com/feeds/videos.xml?channel_id=' . $_GET['channelid']);
        }
        if (isset($_GET['user'])) {
            $dom->load('https://www.youtube.com/feeds/videos.xml?user=' . $_GET['user']);
        }

        $root=$dom->documentElement;
        $rssDom=new \DOMDocument();
	$rssRoot=$rssDom->createElement("rss");
	$rssRoot->setAttribute("version", "2.0");
	$rssRoot->setAttribute("xmlns:itunes", "http://www.itunes.com/dtds/podcast-1.0.dtd");	
	$rssDom->appendChild($rssRoot);
	$rssChannel=$rssDom->createElement("channel");
	$rssRoot->appendChild($rssChannel);

        $title=$root->getElementsByTagName('title')->item(0)->nodeValue;
	$titleElement=$rssDom->createElement("title");
	$titleElement->appendChild($rssDom->createTextNode($title));
	$rssChannel->appendChild($titleElement);

        $url=$root->getElementsByTagName('link')->item(0)->getAttributeNode('href')->nodeValue;
	$linkElement=$rssDom->createElement("link");
	$linkElement->setAttribute("href", $url);
	$rssChannel->appendChild($linkElement);

	$categoryElement=$rssDom->createElement("category");
	$categoryElement->appendChild($rssDom->createTextNode("TV & Film"));
	$rssChannel->appendChild($categoryElement);

	$itunesCategoryElement=$rssDom->createElement("itunes:category");
	$itunesCategoryElement->appendChild($rssDom->createTextNode("TV & Film"));
	$rssChannel->appendChild($itunesCategoryElement);

	$itunesSubtitleElement=$rssDom->createElement("itunes:subtitle");
	$itunesSubtitleElement->appendChild($rssDom->createTextNode($title));
	$rssChannel->appendChild($itunesSubtitleElement);

	$generatorElement=$rssDom->createElement("generator");
	$generatorElement->appendChild($rssDom->createTextNode("Youtube RSS Generator"));
	$rssChannel->appendChild($generatorElement);

        $entries=$root->getElementsByTagName('entry');

	// TODO image, description, updated, published
        
        // Loop trough childNodes
        foreach ($entries as $entry) {
            $item = $rssDom->createElement("item");
            $rssChannel->appendChild($item);
            
            $url=$entry->getElementsByTagName('link')->item(0)->getAttributeNode('href')->nodeValue;
            $title=$entry->getElementsByTagName('title')->item(0)->nodeValue;
            
            $video_id = substr(parse_url($url, PHP_URL_QUERY), 2);
            $video_info = $youtube_provider->provide($video_id);
            $full_info = $this->getFullInfoByFormat($video_info, $_GET['format']);
            if ($full_info != null) {
                $redirect_url = $full_info->getUrl();
                $type = $full_info->getType();
            
           
                $size = $this->getSize($redirect_url, $config, $toolkit);
                // an enclosure element must have the attributes: url, length and type
                $enclosure_url = $rssDom->createAttribute('url');
                $enclosure_url->
                    appendChild($rssDom->createTextNode($site . 'getvideo.php?videoid='
                                                     . $video_info->getVideoId() . '&format=' . $_GET['format']));
                $enclosure_length = $rssDom->createAttribute('length');
                $enclosure_length->appendChild($dom->createTextNode($size));
                $enclosure_type = $rssDom->createAttribute('type');
                $enclosure_type->appendChild($dom->createTextNode($type));

                $enclosure = $rssDom->createElement('enclosure');
                $enclosure->appendChild($enclosure_url);
                $enclosure->appendChild($enclosure_length);
                $enclosure->appendChild($enclosure_type);

                $entry->appendChild($enclosure);
                
                
            }

            $guid = $rssDom->createElement("guid");
            $guid->appendChild($rssDom->createTextNode($video_id));
            $item->appendChild($guid);

            $itemTitle = $rssDom->createElement("title");
            $itemTitle->appendChild($rssDom->createTextNode($entry->getElementsByTagName('title')->item(0)->nodeValue));
            $item->appendChild($itemTitle);
            
            $description = $rssDom->createElement("description");
            $description->appendChild($rssDom->createTextNode($entry->getElementsByTagName('media:group')->item(0)->getElementsByTagName('media:description')->item(0)->nodeValue));
            $item->appendChild($description);
            
            $link = $rssDom->createElement("link");
            $link->appendChild($rssDom->createTextNode($entry->getElementsByTagName('link')->item(0)->getAttributeNode('href')->nodeValue));
            $item->appendChild($link);
        
            // pubDate
            // 
            ///       <itunes:subtitle>Third Parties: Last Week Tonight with John Oliver (HBO)</itunes:subtitle>
      //<itunes:image href="https://i.ytimg.com/vi/k3O01EfM5fU/maxresdefault.jpg"></itunes:image>
      //<itunes:duration>18:39</itunes:duration>
      //<itunes:order>49</itunes:order>

            

        }
        header('Content-Type: text/xml; charset=utf-8', true);
        echo $rssDom->saveXML();

        exit;
    }
}
