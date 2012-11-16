<?php

	#################################################################

	function solr_machinetags_prepare_for_path_hierarchy_field($mt, $more=array()){

		$defaults = array(
			'add_lazy_8s' => 1,
		);

		$more = array_merge($defaults, $more);

		$parts = solr_machinetags_explode($mt);

		if ($more['add_lazy_8s']){
			$parts = solr_machinetags_lazy8ify_list($parts);
		}

		return implode("/", $parts);
	}

	#################################################################

	function solr_machinetags_prepare_for_multivalue_field($mt, $more=array()){

		$defaults = array(
			'add_lazy_8s' => 1,
		);

		$more = array_merge($defaults, $more);

		list($ns, $pred, $value) = solr_machinetags_explode($mt);

		$parts = array(
			"{$ns}:",
			"{$ns}:{$pred}=",
			"{$ns}:{$pred}={$value}",
			"={$value}",
			":{$pred}=",
			":{$pred}={$value}",
		);

		if ($more['add_lazy_8s']){
			$parts = solr_machinetags_lazy8ify_list($parts);
		}

		return $parts;
	}

	#################################################################

	function solr_machinetags_explode($mt, $more=array()){

		list($ns, $rest) = explode(":", $mt, 2);
		list($pred, $value) = explode("=", $rest, 2);

		return array($ns, $pred, $value);
	}

	#################################################################

	function solr_machinetags_lazy8ify_list(&$parts){

		$enc_parts = array();

		foreach ($parts as $str){
			$enc_stuff[] = solr_machinetags_add_lazy8s($str);
		}

		return $enc_stuff;
	}

	#################################################################

	function solr_machinetags_add_lazy8s($str){
		$str = preg_replace("/8/", "88", $str);
		$str = preg_replace("/:/", "8c", $str);
		$str = preg_replace("/\//", "8s", $str);
		return $str;
	}

	#################################################################

	function solr_machinetags_remove_lazy8s($str){

		$str = preg_replace("/8s/", "/", $str);
		$str = preg_replace("/8c/", ":", $str);
		$str = preg_replace("/88/", "8", $str);

		return $str;
	}

	#################################################################

?>
