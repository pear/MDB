<?
/*
 * lob.php
 */

$lobs=array();

class MDB_lob
{
	var $error="";
	var $database=0;
	var $lob;
	var $data="";
	var $position=0;

	function create(&$arguments)
	{
		if(isset($arguments["Data"])) {
			$this->data = $arguments["Data"];
		}
		return(1);
	}

	function destroy()
	{
		$this->data = "";
	}

	function endOfLob()
	{
		return($this->position >= strlen($this->data));
	}

	function readLob(&$data, $length)
	{
		$length = min($length,strlen($this->data)-$this->position);
		$data = substr($this->data, $this->position, $length);
		$this->position += $length;
		return($length);
	}
};

class MDB_result_lob extends MDB_lob
{
	var $result_lob = 0;

	function create(&$arguments)
	{
		if(!isset($arguments["ResultLOB"])) {
			$this->error = "it was not specified a result Lob identifier";
			return(0);
		}
		$this->result_lob = $arguments["ResultLOB"];
		return(1);
	}

	function destroy()
	{
		global $databases;
		$databases[$this->database]->destroyResultLob($this->result_lob);
	}

	function endOfLob()
	{
		global $databases;
		return($databases[$this->database]->endOfResultLob($this->result_lob));
	}

	function readLob(&$data, $length)
	{
		global $databases;
		if(($read_length = $databases[$this->database]->readResultLob($this->result_lob, $data, $length)) < 0) {
			$this->error = $databases[$this->database]->error();
		}
		return($read_length);
	}
};

class MDB_input_file_lob extends MDB_lob
{
	var $file = 0;
	var $opened_file = 0;

	function create(&$arguments)
	{
		if(isset($arguments["File"])) {
			if(intval($arguments["File"]) == 0) {
				$this->error = "it was specified an invalid input file identifier";
				return(0);
			}
			$this->file = $arguments["File"];
		}
		else
		{
			if(isset($arguments["FileName"])) {
				if((!$this->file = fopen($arguments["FileName"],"r"))) {
					$this->error = "could not open specified input file (\"".$arguments["FileName"]."\")";
					return(0);
				}
				$this->opened_file = 1;
			} else {
				$this->error = "it was not specified the input file";
				return(0);
			}
		}		
		return(1);
	}

	function destroy()
	{
		if($this->opened_file) {
			fclose($this->file);
			$this->file = 0;
			$this->opened_file = 0;
		}
	}

	function endOfLob() {
		return(feof($this->file));
	}

	function readLob(&$data, $length)
	{
		if(GetType($data = @fread($this->file, $length))!= "string") {
			$this->error = "could not read from the input file";
			return(-1);
		}
		return(strlen($data));
	}
};

class MDB_output_file_lob extends MDB_lob
{
	var $file = 0;
	var $opened_file = 0;
	var $input_lob = 0;
	var $opened_lob = 0;
	var $buffer_length = 8000;

	function create(&$arguments)
	{
		global $lobs;

		if(isset($arguments["BufferLength"])) {
			if($arguments["BufferLength"] <= 0) {
				$this->error = "it was specified an invalid buffer length";
				return(0);
			}
			$this->buffer_length = $arguments["BufferLength"];
		}
		if(isset($arguments["File"])) {
			if(intval($arguments["File"]) == 0) {
				$this->error = "it was specified an invalid output file identifier";
				return(0);
			}
			$this->file = $arguments["File"];
		} else {
			if(isset($arguments["FileName"])) {
				if((!$this->file = fopen($arguments["FileName"],"w"))) {
					$this->error = "could not open specified output file (\"".$arguments["FileName"]."\")";
					return(0);
				}
				$this->opened_file = 1;
			} else {
				$this->error = "it was not specified the output file";
				return(0);
			}
		}		
		if(isset($arguments["LOB"])) {
			if(!isset($lobs[$arguments["LOB"]])) {
				$this->destroy();
				$this->error = "it was specified an invalid input large object identifier";
				return(0);
			}
			$this->input_lob = $arguments["LOB"];
		} else {
			if($this->database
				&& isset($arguments["Result"])
				&& isset($arguments["Row"])
				&& isset($arguments["Field"])
				&& isset($arguments["Binary"]))
			{
				global $databases;
				if($arguments["Binary"]) {
					$this->input_lob = $databases[$this->database]->fetchBLobResult($arguments["Result"],
						$arguments["Row"], $arguments["Field"]);
				} else {
					$this->input_lob = $databases[$this->database]->fetchClobResult($arguments["Result"],
						$arguments["Row"], $arguments["Field"]);
				}
				if($this->input_lob == 0) {
					$this->destroy();
					$this->error = "could not fetch the input result large object";
					return(0);
				}
				$this->opened_lob = 1;
			} else {
				$this->destroy();
				$this->error = "it was not specified the input large object identifier";
				return(0);
			}
		}		
		return(1);
	}

	function destroy()
	{
		if($this->opened_file) {
			fclose($this->file);
			$this->opened_file = 0;
			$this->file = 0;
		}
		if($this->opened_lob) {
			destroyLob($this->input_lob);
			$this->input_lob = 0;
			$this->opened_lob = 0;
		}
	}

	function endOfLob()
	{
		return(endOfLob($this->input_lob));
	}

	function readLob(&$data, $length) {
		$buffer_length = ($length == 0 ? $this->buffer_length : $length);
		for($written = 0;
		!endOfLob($this->input_lob) && $written<$buffer_length;
		$written += $read) {
			if(readLob($this->input_lob, $buffer, $buffer_length) == -1) {
				$this->error = lob($this->input_lob);
				return(-1);
			}
			$read = strlen($buffer);
			if(@fwrite($this->file, $buffer, $read)!= $read) {
				$this->error = "could not write to the output file";
				return(-1);
			}
		}
		return($written);
	}
};

function createLOB(&$arguments, &$lob)
{
	global $lobs;

	$lob = count($lobs)+1;
	$class = "MDB_lob";
	if(isset($arguments["Type"])) {
		switch($arguments["Type"]) {
			case "resultlob":
				$class="MDB_result_lob";
				break;
			case "inputfile":
				$class="MDB_input_file_lob";
				break;
			case "outputfile":
				$class="MDB_output_file_lob";
				break;
			default:
				if(isset($arguments["Error"])) {
					$arguments["Error"] = $arguments["Type"]." is not a valid type of large object";
				}
				return(0);
		}
	} else {
		if(IsSet($arguments["Class"])) {
			$class = $arguments["Class"];
		}
	}
	$lobs[$lob] = new $class;
	$lobs[$lob]->lob = $lob;
	if(isset($arguments["Database"])) {
		$lobs[$lob]->database = $arguments["Database"];
	}
	if($lobs[$lob]->create($arguments)) {
		return(1);
	}
	if(isset($arguments["Error"])) {
		$arguments["Error"] = $lobs[$lob]->error;
	}
	destroyLob($lob);
	return(0);
}

function destroyLob($lob)
{
	global $lobs;

	$lobs[$lob]->destroy();
	unset($lobs[$lob]);
	$lobs[$lob] = "";
}

function endOfLob($lob)
{
	global $lobs;
	return($lobs[$lob]->endOfLob());
}

function readLob($lob, &$data, $length)
{
	global $lobs;

	return($lobs[$lob]->readLob($data, $length));
}

function lobError($lob)
{
	global $lobs;

	return($lobs[$lob]->error);
}

?>
