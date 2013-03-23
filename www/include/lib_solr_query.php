<?php

	function solr_query_parse($q, $field=null){

		#
		# build a tree
		#

		$tree = solr_query__tree($q);


		#
		# turn each layer into a flattened query
		#

		$query = solr_query__resolve($tree, $field);

		return $query;
	}

	function solr_query_escape($str){

		return preg_replace('!([+\-&|\!(){}\[\]^\"~*?\\\\:])!', '\\\\$1', $str);
	}

	function solr_query__tree($q){

		#
		# tokenize the query first
		#

		$pos = 0;
		$len = strlen($q);

		$tokens = array();

		while ($pos < $len){

			# skip whitespaces
			if (preg_match('!^\s+!', substr($q, $pos), $m)){
				$pos += strlen($m[0]);
				continue;
			}

			# words which mean things
			if (preg_match('!^(and|or|not)!i', substr($q, $pos), $m)){
				$pos += strlen($m[0]);
				$tokens[] = array(StrToUpper($m[0]), $m[1]);
				continue;
			}

			# brackets
			if (preg_match('!^[()]!', substr($q, $pos), $m)){
				$pos += strlen($m[0]);
				$tokens[] = array($m[0]);
				continue;
			}

			# phrases
			if (preg_match('!^(\+|-)?"([^"]+)"!', substr($q, $pos), $m)){
				$pos += strlen($m[0]);
				$tokens[] = array($m[1].'TERM', $m[1].'"'.solr_query_escape($m[2]).'"');
				continue;
			}

			# terms
			if (preg_match('!^(\+|-)?([^\s()]+)!', substr($q, $pos), $m)){
				$pos += strlen($m[0]);
				$tokens[] = array($m[1].'TERM', $m[1].solr_query_escape($m[2]));
				continue;
			}

			# this should never be reached
			$pos++;
		}


		#
		# turn this into a tree, based on brackets
		#

		$tree = solr_query__tree_group($tokens, true);

		return $tree[1];
	}

	function solr_query__tree_group(&$tokens, $root){

		#
		# process sub groups
		#

		$out = array();

		while (count($tokens)){

			$next = array_shift($tokens);
			if ($next[0] == '('){
				$out[] = solr_query__tree_group($tokens, false);
			}elseif ($next[0] == ')'){
				if ($root){
					$out[] = array('TERM', solr_query_escape($next[0]));
				}else{
					break;
				}
			}else{
				$out[] = $next;
			}
		}

		return array('GROUP', $out);
	}


	function solr_query__resolve($tokens, $field){

		#
		# resolve sub groups first
		#

		$terms = array();
		foreach ($tokens as $token){
			if ($token[0] == 'GROUP'){
				$terms[] = array('GROUP', solr_query__resolve($token[1], $field));
			}else{
				$terms[] = $token;
			}
		}


		#
		# now turn our flat list of tokens into a query.
		# at this point it may contain tokens of type:
		#
		# TERM, GROUP, +TERM, -TERM, AND, OR, NOT
		#


		#
		# get rid of NOT terms, then ANDs, finally ORs
		#

		$terms = solr_query__join_not($terms);
		$terms = solr_query__collapse_bool($terms, 'AND');
		$terms = solr_query__collapse_bool($terms, 'OR');


		#
		# if we have a single term, return it
		#

		if (count($terms) == 1){
			$term = array_pop($terms);
			return $term[1];
		}


		#
		# split apart normal and plus/minus terms
		#

		$norm_terms = array();
		$plus_terms = array();

		foreach ($terms as $term){
			if ($term[0] == 'TERM' ) $norm_terms[] = $term[1];
			if ($term[0] == 'GROUP') $norm_terms[] = $term[1];
			if ($term[0] == '-TERM') $plus_terms[] = $term[1];
			if ($term[0] == '+TERM') $plus_terms[] = $term[1];
		}

		$nn = count($norm_terms);
		$np = count($plus_terms);


		#
		# simple case - all normal or all plus
		#

		if ($nn && !$np) return '(' . implode(' AND ', $norm_terms) . ')';
		if (!$nn && $np) return '(' . implode(' ', $plus_terms) . ')';

		
		#
		# combine regular and plus/minus terms
		#

		$norms = '('.implode(' AND ', $norm_terms).')';
		if ($nn == 1) $norms = array_pop($norm_terms);

		$pluses = implode(' ', $plus_terms);

		return "({$pluses} {$norms})";
	}


	#
	# NOTs bind to the TERM after them, if it exists. "foo not bar"
	# Otherwise, we can convert it to a term.
	#

	function solr_query__join_not($in){

		$out = array();

		while (count($in)){
			$t = array_shift($in);
			if ($t[0] == 'NOT'){
				if (count($in)){
					$next = array_shift($in);
					if ($next[0] == 'TERM'){
						$out[] = array('-TERM', '-'.$next[1]);
					}elseif ($next[1] == 'GROUP'){
						$out[] = array('GROUP', '(NOT '.$next[1].')');
					}else{
						$out[] = array('TERM', '"'.solr_query_escape($t[1]).'"');
						array_unshift($in, $next);
					}
				}else{
					$out[] = array('TERM', '"'.solr_query_escape($t[1]).'"');
				}
			}else{
				$out[] = $t;
			}
		}

		return $out;
	}


	function solr_query__collapse_bool($in, $op){

		$term_types = array('TERM', 'GROUP', '+TERM', '-TERM');

		$out = array();
		$prev = null;

		while (count($in)){
			$t = array_shift($in);

			if ($t[0] == $op){

				$type_prev = '';
				$type_next = '';

				if (count($out)) $type_prev = $out[count($out)-1][0];
				if (count($in))  $type_next = $in[0][0];

				if (in_array($type_prev, $term_types) && in_array($type_next, $term_types)){

					$prev = array_pop($out);
					$next = array_shift($in);

					$out[] = array('GROUP', "({$prev[1]} {$op} {$next[1]})");
					
				}else{
					$out[] = array('TERM', '"'.solr_query_escape($t[1]).'"');
				}

			}else{
				$out[] = $t;
			}
		}

		return $out;
	}
