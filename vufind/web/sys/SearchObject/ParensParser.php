<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

// Started with code example from
// @rodneyrehm
// http://stackoverflow.com/a/7917979/99923
class ParensParser {
	// something to keep track of parens nesting
	protected $stack = null;


	// input string to parse
	protected $string = null;
	// current character offset in string
	protected $position = null;
	// start of text-buffer
	protected $buffer_start = null;

	private int $currentDepth = 0;
	public int $maxDepth = 0;


	function parse(string $string) : array {
		if (!$string){
			// no string, no data
			return [];
		}

		$this->stack   = [];

		$this->string = $string;
		$this->length = strlen($this->string);
		// look at each character
		for ($this->position = 0;$this->position < $this->length;$this->position++){
			switch ($this->string[$this->position]){
				case '(':
					$stringSoFar = $this->getStringSoFar();
					if (!empty($stringSoFar)){
						$this->stack[] = $stringSoFar;
					}
					$this->currentDepth++;
					if ($this->currentDepth > $this->maxDepth){
						$this->maxDepth = $this->currentDepth;
					}
					$subClause          = $this->getRestOfString();
					$parser             = new ParensParser();
					$aStack             = $parser->parse($subClause);
					$this->stack[]      = $aStack;
					$this->position     += $this->arrayStrLens($aStack);
					$this->buffer_start = null;
					$totalDepth = $this->currentDepth + $parser->maxDepth;
					if ($totalDepth > $this->maxDepth){
						$this->maxDepth = $totalDepth;
					}
					$this->currentDepth--;
					break;
				case ')':
					if ($this->buffer_start !== null){
						$stringSoFar1  = $this->getStringSoFar();
						$this->stack[] = $stringSoFar1;
						return $this->stack;
					}
					break;
				default:
					// remember the offset to do a string capture later
					// could've also done $buffer .= $string[$position]
					// but that would just be wasting resources…
					if ($this->buffer_start === null){
						$this->buffer_start = $this->position;
					}
			}
		}
		return $this->stack;
	}

	private function arrayStrLens($array){
		$length = 1;  //This presumes a 1 for each trailing ) we will want to skip over
		foreach ($array as $value){
			if (is_array($value)){
				$length += $this->arrayStrLens($value);
			}
			if (is_string($value)){
				$length += strlen($value);
			}
		}
		return $length;
	}

	protected function getStringSoFar(){
		return substr($this->string, $this->buffer_start, $this->position - $this->buffer_start);
	}

	protected function getRestOfString(){
		return substr($this->string, $this->position+1);
	}
}