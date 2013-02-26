/* featuredbooks.js   */
/* Daniel Zimmel 2013 */


// Configuration: set your values here.
var mylib = 'rdg01';
var myccl = 'wab=featuredbook';

$(document).ready(function() {

// loading message 
$('body').append('<p id="fetching">fetching from '+mylib+'...</p>');

// get Aleph-X
$.ajax({url:'http://www.coll.mpg.de/bib/beta/temp/rss.php?myquery='+myccl+'&mybase='+mylib+'&output=json',

dataType: 'jsonp', 
success: function(data) { 

						$('#fetching').remove();

						var itemData = data.channel.item;

						//	for(var i =0;i < itemData.length-1;i++) // alle Titel ausgeben (max. 100)
						for(var i =0;i < itemData.length && i < 5;i++) // nur die aktuellsten 5 Titel ausgeben
								{
										if (typeof itemData[i].guid == 'string') {
												var isbn = itemData[i].guid;} else { var isbn = undefined; }
									
										if (isbn) {

									 // var title_short = itemData[i].title.slice(0,90) + "...";

										$('body').append('<div class="item" id="item'+i+'"><a href="'+itemData[i].link+'"><img src="" class="content" title="'+itemData[i].title+'" /></a></div>');


										$('#item'+i).each( function() {
														//				console.log(i);


										$.ajax({url: 'http://books.google.com/books?bibkeys=ISBN:'+isbn+'&jscmd=viewapi',
												dataType: 'jsonp',
												//context: {i:i}, // set current iterator (async!)
											  i: i,
												success: function(booksInfo) {
																for (id in booksInfo) {
																		isbn = id;
																		//console.log(booksInfo[id].thumbnail_url);
																		//console.log(this.i);
																		$('#item'+this.i).find('img').attr('src', booksInfo[id].thumbnail_url);
																}
														}
												});
												});
								}
								}
					
				}
		});
		});