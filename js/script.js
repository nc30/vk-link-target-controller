document.addEventListener("DOMContentLoaded",function(e){
var $=jQuery;
$.post(
	vkLtc.ajaxurl,{action : 'ids',},
	function(ps) {
		if(!$.isEmptyObject(ps)){
			$.each(ps, function(id, ls) {
				try{
				ls.forEach(function(l){
					var c = $('#post-'+id+' a[href="'+l+'"]');
					if(c.length){$(c).attr('target','_blank');}
				});
				}finally{}
			});
		}
	}
);},false);
