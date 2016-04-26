(function($, d, w, undefined) {

	/**
	 * Basic plugins
	 */
	$.fn.spHide = function() {
	    return this.hide().attr( 'aria-hidden', true ).removeClass('secupress-open');
	};
	$.fn.spFadeIn = function() {
	    return this.fadeIn(300, function(){
	    	$(this).addClass('secupress-open');
	    }).attr( 'aria-hidden', false );
	};
	$.fn.spFadeOut = function() {
	    return this.fadeOut(300, function(){
	    	$(this).removeClass('secupress-open');
	    }).attr( 'aria-hidden', true );
	};
	$.fn.spSlideDown = function() {
	    return this.slideDown(400, function(){
	    	$(this).addClass('secupress-open');
	    }).attr( 'aria-hidden', false );
	};
	$.fn.spSlideUp = function() {
	    return this.slideUp(400, function(){
	    	$(this).removeClass('secupress-open');
	    }).attr( 'aria-hidden', true );
	};
	$.fn.spAnimate = function( effect ) {
		var effect = effect || 'fadein';

		switch ( effect ) {
			case 'fadein' :
				this.spFadeIn();
				break;
			case 'fadeout' :
				this.spFadeOut();
				break;
			case 'slidedown' :
				this.spSlideDown();
				break;
			case 'slideup' :
				this.spSlideUp();
				break;
		}
		return this;
	}

	/**
	 * Tabs
	 * @author : Geoffrey
	 */
	
	$('.secupress-tabs').each( function(){

		var $tabs		= $(this),
			$content 	= $tabs.data('content') ? $( $tabs.data('content') ) : $tabs.next(),
			$tab_content= $content.find('.secupress-tab-content'),
			$current 	= $tabs.find('.secupress-current').lenght ? $tabs.find('.secupress-current') : $tabs.find('a:first'),

			set_current = function( $item ) {
				$item.closest('.secupress-tabs').find('a').removeClass('secupress-current').attr('aria-selected', false);
				$item.addClass('secupress-current').attr('aria-selected', true);
			},
			change_tab = function( $item ) {
				$tab_content.spHide();
				$( '#' + $item.attr('aria-control') ).spFadeIn();
			}

		$tab_content.hide();

		$tabs.find('a').on( 'click.secupress', function() {
			set_current( $(this) );
			change_tab( $(this) );
			return false;
		} );

		$current.trigger('click.secupress');

	} );


	/**
	 * Triggering (slidedown, fadein, etc.)
	 * @author: Geoffrey
	 */
	
	$('[data-trigger]').each(function(){

		// init
		var $_this	= $(this),
			target	= $_this.data('target'),
			$target	= $( '#' + target ),
			effect	= $_this.data('trigger');

		$target.spHide();

		// click
		$_this.on( 'click.secupress', function(){
			
			$target.spAnimate( effect );

			if ( effect == 'slideup' || effect == 'fadeout') {
				$( '[data-target="' + target + '"]').filter('.secupress-activated').removeClass('secupress-activated')
			} else {
				$(this).addClass('secupress-activated');
			}
			return false;
		} );

	});

} )(jQuery, document, window);