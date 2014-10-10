
(function($) {	
	
	$(function() {
		var options = $.extend({
			'fieldName': '#s',
			'maxRows': 10,
			'minLength': 3	,
			'autocomplete_alert' : "Почніть вводити вулицю, і оберіть адресу зі списку, що з'явиться"
		}, ZaKogoAutocomplete);

		options.fieldName = $('<div />').html(options.fieldName).text();
		
		$(options.fieldName).parents('form').submit( function( event ) {
			// don't allow submit form
			alert(options.autocomplete_alert);
			event.preventDefault();
		});
		
		$(options.fieldName).autocomplete({
			source: function( request, response ) {
				$('#empty_result').remove();
			    $.ajax({
			        url: getZaFilterUrl(),			    	
			        dataType: "json",
			        success: function( data ) {
			        	if( data.results.length == 0 ) {
			        		$(options.fieldName).after("<div id='empty_result' style='width: 100%; margin-top: 3px; padding: 3px 10px;' class='ui-widget ui-widget-content ui-corner-all'><div class='zakogo-ac-previous'>Жодної адреси за Вашим запитом не знайдено. Спробуйте ввести найбільш унікальну частину адреси (наприклад, для 'вулиця Академіка <b>Туполєв</b>а' вводіть '<b>туполєв</b>')</div></div>");			        		
			        	}
			            response( $.map( data.results, function( item ) {
			                return {
			                	label: item.title,
			                	value: item.title,
			                	url: item.url,
			                	region: item.region
			                }
			            }));
			        },
			        error: function(jqXHR, textStatus, errorThrown) {
			        	console.log(jqXHR, textStatus, errorThrown);
			        }
			    });
			},
			minLength: options.minLength,
			delay: 600,
			search: function(event, ui) {
				$(event.currentTarget).addClass('sa_searching');
			},
			create: function() {
				var prev_street = readCookie('zakogo_street');
				var prev_url = readCookie('zakogo_url');
				if( prev_street && prev_url ) {
					$(this).after("<div class='zakogo-ac-previous'>Ваш попередній вибір: <a href='" + prev_url + "'>" +prev_street+ "</a></div>");
				}				
			},
			select: function( event, ui ) {
                if ( ui.item.url !== '#' ) {
                		createCookie('zakogo_street', ui.item.label,7);
                		createCookie('zakogo_url', ui.item.url,7);
        				$(this).after("<div style='width: 100%; margin-top: 3px; padding: 3px 10px;' class='ui-widget ui-widget-content ui-corner-all'><div class='zakogo-ac-previous ui-autocomplete-loading'>Завантажується сторінка...</div></div>");
                        location = ui.item.url;
                        $(event.currentTarget).addClass('sa_searching');
                } else {
                        return true;
                }
			},
			open: function(event, ui) {
				var acData = $(this).data('uiAutocomplete');
				acData
						.menu
						.element
						.find('.zakogo-ac-list-street')
						.each(function () {
							var me = $(this);
							var keywords = acData.term.split(' ').join('|');
							me.html(me.text().replace(new RegExp("(" + keywords + ")", "gi"), '<span class="zakogo-ac-found-text">$1</span>'));
						});
				$(event.target).removeClass('sa_searching');
			},
			close: function() {
			}
		}).data( "ui-autocomplete" )._renderItem = function( ul, item ) {
			return $( "<li class='ui-corner-all'>" ).hover(
				function() {
					$( this ).addClass('ui-state-focus');
				}, function() {
					$( this ).removeClass('ui-state-focus');
				}
			)
			.append( "<div class='zakogo-ac-list-street'>" + item.label + "</div><div class='zakogo-ac-region'>" + item.region + "</div>" )
			.appendTo( ul ).
			click( function ( event ) {
				$(options.fieldName).val( item.label ).data('ui-autocomplete')
									._trigger(
											'select', 'autocompleteselect', {'item':item}
									);				
				$(".ui-autocomplete").hide();
			});
		};
		
		$(".ui-autocomplete").addClass('zakogo-ac-list').css("overflow-y", "auto").css("overflow-x", "hidden");
		
		function getZaFilterUrl() {
			var ret = options.ajaxurl;
			if( options.cacheurl ) {
				var str = $(options.fieldName).val();
				ret = options.cacheurl + '/0_' + Base64.encode(str.toLowerCase()) ;
			}
						return ret;
		}
	});

	function createCookie(name, value, days) {
	    var expires;

	    if (days) {
	        var date = new Date();
	        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
	        expires = "; expires=" + date.toGMTString();
	    } else {
	        expires = "";
	    }
	    document.cookie = escape(name) + "=" + escape(value) + expires + "; path=/";
	}

	function readCookie(name) {
	    var nameEQ = escape(name) + "=";
	    var ca = document.cookie.split(';');
	    for (var i = 0; i < ca.length; i++) {
	        var c = ca[i];
	        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
	        if (c.indexOf(nameEQ) === 0) return unescape(c.substring(nameEQ.length, c.length));
	    }
	    return null;
	}

	function eraseCookie(name) {
	    createCookie(name, "", -1);
	}	

})(jQuery);

/**
*
*  Base64 encode / decode
*  http://www.webtoolkit.info/
*
**/
var Base64 = {

// private property
_keyStr : "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",

// public method for encoding
encode : function (input) {
    var output = "";
    var chr1, chr2, chr3, enc1, enc2, enc3, enc4;
    var i = 0;

    input = Base64._utf8_encode(input);

    while (i < input.length) {

        chr1 = input.charCodeAt(i++);
        chr2 = input.charCodeAt(i++);
        chr3 = input.charCodeAt(i++);

        enc1 = chr1 >> 2;
        enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
        enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
        enc4 = chr3 & 63;

        if (isNaN(chr2)) {
            enc3 = enc4 = 64;
        } else if (isNaN(chr3)) {
            enc4 = 64;
        }

        output = output +
        this._keyStr.charAt(enc1) + this._keyStr.charAt(enc2) +
        this._keyStr.charAt(enc3) + this._keyStr.charAt(enc4);

    }

    return output;
},

// public method for decoding
decode : function (input) {
    var output = "";
    var chr1, chr2, chr3;
    var enc1, enc2, enc3, enc4;
    var i = 0;

    input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");

    while (i < input.length) {

        enc1 = this._keyStr.indexOf(input.charAt(i++));
        enc2 = this._keyStr.indexOf(input.charAt(i++));
        enc3 = this._keyStr.indexOf(input.charAt(i++));
        enc4 = this._keyStr.indexOf(input.charAt(i++));

        chr1 = (enc1 << 2) | (enc2 >> 4);
        chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
        chr3 = ((enc3 & 3) << 6) | enc4;

        output = output + String.fromCharCode(chr1);

        if (enc3 != 64) {
            output = output + String.fromCharCode(chr2);
        }
        if (enc4 != 64) {
            output = output + String.fromCharCode(chr3);
        }

    }

    output = Base64._utf8_decode(output);

    return output;

},

// private method for UTF-8 encoding
_utf8_encode : function (string) {
    string = string.replace(/\r\n/g,"\n");
    var utftext = "";

    for (var n = 0; n < string.length; n++) {

        var c = string.charCodeAt(n);

        if (c < 128) {
            utftext += String.fromCharCode(c);
        }
        else if((c > 127) && (c < 2048)) {
            utftext += String.fromCharCode((c >> 6) | 192);
            utftext += String.fromCharCode((c & 63) | 128);
        }
        else {
            utftext += String.fromCharCode((c >> 12) | 224);
            utftext += String.fromCharCode(((c >> 6) & 63) | 128);
            utftext += String.fromCharCode((c & 63) | 128);
        }

    }

    return utftext;
},

// private method for UTF-8 decoding
_utf8_decode : function (utftext) {
    var string = "";
    var i = 0;
    var c = c1 = c2 = 0;

    while ( i < utftext.length ) {

        c = utftext.charCodeAt(i);

        if (c < 128) {
            string += String.fromCharCode(c);
            i++;
        }
        else if((c > 191) && (c < 224)) {
            c2 = utftext.charCodeAt(i+1);
            string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
            i += 2;
        }
        else {
            c2 = utftext.charCodeAt(i+1);
            c3 = utftext.charCodeAt(i+2);
            string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
            i += 3;
        }

    }

    return string;
}

}