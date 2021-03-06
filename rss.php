<?php
if ($_GET['output'] == 'json') {
	header('content-type: application/json; charset=utf-8');
	header("access-control-allow-origin: *");
} else {
header ("Content-Type:text/xml");
}
// based on: http://www.exlibrisgroup.org/display/AlephCC/Aleph+RSS+feeds+%28PHP+script%29
// Original code by Thomas McNulty 2010, Virginia Commonwealth University
// f. Aleph/MPG Daniel Zimmel 2011-2014
// License: BSD style
// Short description: Use, modification and distribution of the code are permitted provided 
// the copyright notice, list of conditions and disclaimer appear in all related material.
//
// you need X-server permissions
  
// Base eintragen, bzw. wird abgefragt
//$base = "rdg01";
$base = htmlspecialchars($_GET['mybase']);
$myquery = htmlspecialchars($_GET['myquery']);
$myquery = str_replace('&quot;','"',$myquery); // Rueckumwandlung, wegen Phrasensuche (ist das Unsinn?)
//$myquery = str_replace("Words= ","WRD=",$myquery);
//$myquery = preg_replace("/\sand.+/","",$myquery);

if (empty($base)) { exit("missing library base, exiting");}
else if (empty($myquery)) {exit("missing query, exiting");}

// Bibnamen auslesen, werden unten zugeordnet und in Feed ausgegeben als Titel:
include('bibnames.inc.php');
if ($bibnames[$base] != false) {
	$bibname = $bibnames[$base];
} else { exit("library base not found in bibnames, exiting"); }

//Change library_url to match the base url of your catalog
$library_url = "http://aleph.mpg.de";
$vufind_base = "http://core.coll.mpg.de";

// Change as needed to match your catalog X-server location - please consult the X-services documentation
// for help in setting up the X-server
$base_url = $library_url."/X?op=find&base=".$base;

//Sort variables
$sort_url = $library_url."/X?op=sort-set&library=".$base;

// In order to sort, sort codes must be used from the Aleph // xxx01/tab/tab_sort table which uses
// the filing procedure defined at xxx01/tab/tab_filing
// Please consult the files above for help in setting this up
// Voraussetzung fuer eine funktionierende Sortierung nach Datum: in tab_sort muss bei *allen* folgender Eintrag stehen
// 11 99 003#1         002#1                                                   00 00
// (nach Aenderungen in tab_sort muss p-manage-27 ausgeloest werden)
$sort_code = "&sort_code_1=11&sort_order_1=D&sort_code_2=03&sort_order_2=D"; // sortiert vor nach MAB 003/002

//Present variables
$display_url = $library_url."/X?op=present&base=".$base;
$set_entry = "&set_entry=001-";

//For RSS creation
// Most of this RSS generation code comes from Peter Skalar's book PHP 5
// All you really need to know is that it is the framework for creating the RSS feed
// Best to leave this part alone if you don't know what you're doing

//Constructs URL to link item in feed to item in catalog
class RSS extends DomDocument {
	
	function __construct($title, $link, $description, $ttl) {
        // Set this document up as XML 1.0 with a root
        // <rss> element that has a version="0.91" attribute
        parent::__construct('1.0');
        $rss = $this->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $this->appendChild($rss);

        // Create a <channel> element with <title>, <link>,
        // and <description> sub-elements
        $channel = $this->createElement('channel');
        $channel->appendChild($this->makeTextNode('title', $title));
        $channel->appendChild($this->makeTextNode('link', $link));
        $channel->appendChild($this->makeTextNode('description', $description));
        $channel->appendChild($this->makeTextNode('ttl', $ttl)); // time to live (try to reduce traffic, for caching)

        // Add <channel> underneath <rss>
        $rss->appendChild($channel);

        // Set up output to print with linebreaks and spacing
        $this->formatOutput = true;
	}

    // This function adds an <item> to the <channel>
	function addItem($title, $link, $description, $pubDate, $guid) {
        // Create an <item> element with <title>, <link>
        // and <description> sub-elements
        $item = $this->createElement('item');
        $item->appendChild($this->makeTextNode('title', $title));
        $item->appendChild($this->makeTextNode('link', $link));
        $item->appendChild($this->makeTextNode('description', $description));
        $item->appendChild($this->makeTextNode('pubDate', $pubDate));
        $item->appendChild($this->makeTextNode('guid', $guid));

        // Add the <item> to the <channel>
        $channel = $this->getElementsByTagName('channel')->item(0);
        $channel->appendChild($item);
    }

	function addItems($items) {
	$item_url = $library_url."/F/?func=find-c&ccl_term=sys=";
		foreach ($items as $item) {
			$this->addItem($item->title, $item_url.$item->number, $item->subtitle);
        }
    }

    // A helper function to make elements that consist entirely
    // of text (no sub-elements)
    private function makeTextNode($name, $text) {
        $element = $this->createElement($name);
        $element->appendChild($this->createTextNode($text));
        return $element;
    }
}


// the rss feeds we're generating need 4 things (title, link, description, and a filename) but we're only going to pull 3 here
// filename, feed title, and a URL that will hit the Aleph X-server with the request we're looking for
// then in the next section with the set number from the request we'll get the other information

//change to the url of your library's X server	
$library_xurl= $library_url."/X";

$feed = new stdClass();
$feed->filename = 'neu.myquery.xml';
// falls alle Neuerwerbungen gewaehlt, dann anderer Titel (s. mpg-js/mpg-funcs-extra.lng.js)
if ($myquery == "wab=new-acq") {
	$mydaterequest = date('ym')."+OR+".date('ym',strtotime("-1 Months"));
	$feed->feed_title = 'Recent acquisitions ('.$bibname.')';
	$feed->request_url = $library_xurl."?op=find&base=".$base."&request=(WAB=".$mydaterequest.")";
} else {
	$feed->feed_title = 'Catalog updates for: "'.substr($myquery,4).'" ('.$bibname.')';
	$feed->request_url = $library_xurl."?op=find&base=".$base."&request=(".$myquery.")";
}
$arr_feeds[]=$feed;

/* $feed = new stdClass(); */
/* $feed->filename = 'elecnuc.rss'; */
/* $feed->feed_title = 'Electrical and nuclear engineering,  Chemical technology'; */
/* $feed->request_url = $library_xurl."?op=find&base=".$base."&request=(sig=bap->bap%20d%20200)"; */
/* $arr_feeds[]=$feed; */

// in the large foreach loop below you can change the xpath queries to more closely match the cataloging at your library
// for example you can change the 245 subfield b to c if you'd rather have the author listed instead of the subtitle
// (if that's how items are cataloged at your library - consult your local cataloger if you want to find out more)

//loop to create everything
foreach ($arr_feeds as $feed) {
// the next line sets the feed title, change to suit your needs, it needs the three arguements, feed->feed_title, general url and an actual feed title 
	$rss = new RSS($feed->feed_title, 'http://aleph.mpg.de/', 'Updates to the catalog.','240'); // see above function __construct
// The line below launches the first step in the X-services process - it's asking the server for a set of results
// based on the URL entered above in the request_url variable - the server responds with a set number and number of records that
// are captured in variables below	
	$result = simplexml_load_file($feed->request_url);
	$set_number = $result->set_number;
	$record_count = $result->no_records;

	if (empty($record_count))
		continue;
		
// The line below then takes the set number and sorts the results - sorting is more complicated to configure if you're
// need to sort on a field that is not already sortable in your catalog - consult Sort Set X-Service in the Aleph
// X-Services guide and the tab_sort and tab_filing files in your XXX01 library for more information
	
	simplexml_load_file($sort_url."&set_number=".$set_number.$sort_code);
// The line below is the final request sent from this script to the X-server
// This grabs the sorted set and presents them in XML	

	$result = simplexml_load_file($display_url.$set_entry.$record_count."&set_number=".$set_number);
// The loop below grabs the information we need for the RSS feed from the sorted XML document	

	foreach ($result as $record){
	
		// pruefe GG-Status (Z30$$p), z.B. 'SV' oder 'GG' oder 'NP' (expand_doc_bib_z30 muss dafuer in tab_expand f. WWW-X gesetzt sein)
		$gg = $record->xpath("metadata/oai_marc/varfield[@id='Z30']/subfield[@label='p']");
		if (empty($gg)) { // mache weiter nur wenn GG-Status = '' (leer) (= Bestand)

		$doc_number_result = $record->xpath("doc_number");
		$doc_number = $doc_number_result[0];
		$author_result = $record->xpath("metadata/oai_marc/varfield[@id='100']/subfield[@label='a']");
		// $author_spezial = $record->xpath("metadata/oai_marc/varfield[@id='333']/subfield[@label='a']");
		// Autor: falls Autor leer, dann nimm author_spezial (MAB 333)
		$author = (empty($author_result[0]) ? '' :  preg_replace("/[<>]/","",$author_result[0]).": ");
		// Titel Einzelband:
		$title_result = $record->xpath("metadata/oai_marc/varfield[@id='331' and @i2='1']/subfield[@label='a']");
		$subtitle_result = $record->xpath("metadata/oai_marc/varfield[@id='335']/subfield[@label='a']");
		// Titel uebergeordnet:
		$title_mbw_result = $record->xpath("metadata/oai_marc/varfield[@id='331' and @i2='2']/subfield[@label='a']");
		$volume = $record->xpath("metadata/oai_marc/varfield[@id='089']/subfield[@label='a']");
		// Titel konstruieren:
		$title = trim((empty($title_result[0]) ? '' : rtrim((string)preg_replace("/[<>]/","",$title_result[0]),"/")) . (empty($subtitle_result[0]) ? '' :  ": ".rtrim((string)$subtitle_result[0],"/")));
		$title .= (empty($title_mbw_result[0]) ? '' : " (".rtrim((string)preg_replace("/[<>]/","",$title_mbw_result[0]),"/"));
		$title .= (empty($volume[0]) ? '' :  ": ".rtrim((string)$volume[0],"/").") ");
		$callnumber_result = $record->xpath("metadata/oai_marc/varfield[@id='LOC']/subfield[@label='d']");
		$publisher = $record->xpath("metadata/oai_marc/varfield[@id='410']/subfield[@label='a']");
		$year = $record->xpath("metadata/oai_marc/varfield[@id='425']/subfield[@label='a']");
		$notation_result = $record->xpath("metadata/oai_marc/varfield[@id='700']/subfield[@label='a']");
		// zeige bis zu 3 Notationen an:
		$notation = (empty($notation_result[0]) ? '' :  $notation_result[0]).(empty($notation_result[1]) ? '' : ", ". $notation_result[1]).(empty($notation_result[2]) ? '' : ", ".$notation_result[2]);
        // Inhaltsbeschreibungen abgreifen:
        $abstract = $record->xpath("metadata/oai_marc/varfield[@id='750']/subfield[@label='a']");
		// Hier wird die Description konstruiert:
        $description = (empty($publisher[0]) ? '' : $publisher[0]." ").(empty($year[0]) ? '' : $year[0].". ").(empty($callnumber_result[0]) ? '' : " Signatur:&nbsp;".(string)$callnumber_result[0]);
		$description .= (empty($notation) ? '' : ". Notation: ". $notation.".");
        $description .= (empty($description[0]) ? '' : "- Abstract: ". $abstract[0]);
		// bei folgenden Queries muss [1] selektiert werden, da in
		// Aleph-Ausgabe immer das erste Feld fuer die erste Hierarchie
		// steht (sonst default: letztes gefundenes Feld = kann auch
		// uebergeordnetes Feld sein! 
		$pubDate_result = $record->xpath("metadata/oai_marc/varfield[@id='003'][1]/subfield[@label='a']");
		$pubDate_result_2 = $record->xpath("metadata/oai_marc/varfield[@id='002'][1]/subfield[@label='a']");
    $pubDateA = trim((empty($pubDate_result[0]) ? $pubDate_result_2[0] : rtrim((string)$pubDate_result[0]))); // get/trim date
		$pubDate = date("D, d M Y H:i:s T",strtotime($pubDateA)); // format date
		// ISBN in guid schreiben
		$guid_result = $record->xpath("metadata/oai_marc/varfield[@id='540']/subfield[@label='a']");
		$guid = (empty($guid_result[0]) ? '' :  $guid_result[0]);

// The lines below add the informaton to the RSS item		

    if (!empty($title))
			$vufind_url = $vufind_base . "/Record/" . strtoupper($base) . str_pad($doc_number, 21, "0", STR_PAD_LEFT);
      $rss->addItem($author.$title, $vufind_url, $description, $pubDate, $guid);

		}
	}
//saves the output as a file
	//    $rss->save($feed->filename);
//prints the output to the screen

//	print  $rss->saveXML();



if ($_GET['output'] == json) {

function is_valid_callback($subject)
{
    $identifier_syntax
      = '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u';

    $reserved_words = array('break', 'do', 'instanceof', 'typeof', 'case',
      'else', 'new', 'var', 'catch', 'finally', 'return', 'void', 'continue', 
      'for', 'switch', 'while', 'debugger', 'function', 'this', 'with', 
      'default', 'if', 'throw', 'delete', 'in', 'try', 'class', 'enum', 
      'extends', 'super', 'const', 'export', 'import', 'implements', 'let', 
      'private', 'public', 'yield', 'interface', 'package', 'protected', 
      'static', 'null', 'true', 'false');

    return preg_match($identifier_syntax, $subject)
        && ! in_array(mb_strtolower($subject, 'UTF-8'), $reserved_words);
}

function saveJSON ($url) {
		$simplexml = simplexml_load_string($url);
		$json = json_encode($simplexml);
		return $json;
	}

$myrss= $rss->saveXML();

if (is_valid_callback($_GET['callback'])) {
print $_GET['callback'] . "(" . saveJSON($myrss) .")";
} else {
header('status: 400 Bad Request', true, 400);
}

} else {
	// default: RSS
	print $rss->saveXML();

}

}
?>

