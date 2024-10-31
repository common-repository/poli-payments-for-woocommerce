jQuery(function($){
	if( $('#polidefault').length ){
		// Setup
		polidebug=false;
/*
		$('#polimulti h3:empty').hide();
		$('#polimulti p:empty').hide();
*/
		$('#poliuat h3:empty').hide();
		$('#poliuat p:empty').hide();
		$('#polilive h3:empty').hide();
		$('#polilive p:empty').hide();
		$('.wrap.woocommerce h3:empty').hide();
		$('.wrap.woocommerce p:empty').hide();
		
		function woocommerce_poli_redraw(){
			$('#polidefault').show();
/*
			$('#polidefault').hide();
			$('#polimulti').hide();
			poli_multicurrency=$('#woocommerce_poli_multicurrency').is(':checked');
			if( poli_multicurrency ){
				// Multi currency
				$('#polimulti').show();
*/
				reconciliation=[ 'particulars', 'code', 'reference' ];
				for (var i = 0; i < reconciliation.length; i++) {
					selected_id='#woocommerce_poli_nzd'+reconciliation[i];
					freetext_id='#nzd'+reconciliation[i]+'_freetext';
					if( polidebug ) console.log( selected_id+'='+$(selected_id).val() );
					if( $(selected_id).val() == 'freetext' ){
						$(freetext_id).show();
					} else {
						$(freetext_id).hide();
					}
				}
/*
			} else {
				// Default
				$('#polidefault').show();
			}
			if( polidebug ) console.log( 'poli_multicurrency='+poli_multicurrency );
*/
			
			$('#poliuat').hide();
			$('#polilive').hide();
			poli_useuat=$('#woocommerce_poli_useuat').is(':checked');
			if( poli_useuat ){
				$('#poliuat').show();
			} else {
				$('#polilive').show();
			}
			if( polidebug ) console.log( 'poli_useuat='+poli_useuat );
			
			// Multi currency
//			$('#poliuatmulti').show();
			reconciliation=[ 'particulars', 'code', 'reference' ];
			for (var i = 0; i < reconciliation.length; i++) {
				selected_id='#woocommerce_poli_uatnzd'+reconciliation[i];
				freetext_id='#uatnzd'+reconciliation[i]+'_freetext';
				if( polidebug ) console.log( selected_id+'='+$(selected_id).val() );
				if( $(selected_id).val() == 'freetext' ){
					$(freetext_id).show();
				} else {
					$(freetext_id).hide();
				}
			}
		}
		$('#woocommerce_poli_useuat').click(function(){
			if( polidebug ) console.log( 'in woocommerce_poli_useuat click' );
			woocommerce_poli_redraw();
		});		
/*
		$('#woocommerce_poli_multicurrency').click(function(){
			if( polidebug ) console.log( 'in woocommerce_poli_multicurrency click' );
			woocommerce_poli_redraw();
		});
*/
		$('#woocommerce_poli_nzdparticulars').change(function(){
			if( polidebug ) console.log( 'in woocommerce_poli_nzdparticulars change' );
			woocommerce_poli_redraw();
		});		
		$('#woocommerce_poli_nzdcode').change(function(){
			if( polidebug ) console.log( 'in woocommerce_poli_nzdcode change' );
			woocommerce_poli_redraw();
		});		
		$('#woocommerce_poli_nzdreference').change(function(){
			if( polidebug ) console.log( 'in woocommerce_poli_nzdreference change' );
			woocommerce_poli_redraw();
		});		
		$('#woocommerce_poli_uatnzdparticulars').change(function(){
			if( polidebug ) console.log( 'in woocommerce_poli_uatnzdparticulars change' );
			woocommerce_poli_redraw();
		});		
		$('#woocommerce_poli_uatnzdcode').change(function(){
			if( polidebug ) console.log( 'in woocommerce_poli_uatnzdcode change' );
			woocommerce_poli_redraw();
		});		
		$('#woocommerce_poli_uatnzdreference').change(function(){
			if( polidebug ) console.log( 'in woocommerce_poli_uatnzdreference change' );
			woocommerce_poli_redraw();
		});		
		woocommerce_poli_redraw();
	}
});
