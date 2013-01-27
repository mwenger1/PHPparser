<?php
include 'include/db_config.php';
include 'include/chromePHP.php';

chromePHP::log(($con) ? 'db connected' : 'db could not connect');

$q = mysqli_query($con, "SELECT `notes`,`id` from quotetimes as q ORDER BY RAND() LIMIT 0, 1000");
// OPTIONAL LIMIT:  LIMIT 0, 100 ORDER BY q.notes
chromePHP::log(($q) ? 'query submitted' : 'query could not submit');
?>

<!DOCTYPE >
<head>
	<title>Parser Demo</title>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
	<script src="http://ajax.aspnetcdn.com/ajax/jquery.dataTables/1.9.4/jquery.dataTables.js"></script>

	<link rel="stylesheet" href="http://ajax.aspnetcdn.com/ajax/jquery.dataTables/1.9.4/css/jquery.dataTables.css" type="text/css" media="screen" title="no title" charset="utf-8"/>
	<link rel="stylesheet" href="http://ajax.aspnetcdn.com/ajax/jquery.dataTables/1.9.4/css/jquery.dataTables_themeroller.css" type="text/css" media="screen" title="no title" charset="utf-8"/>
	<script type="text/javascript">
		$(document).ready(function() {
			$('#parseTable').dataTable({
				"bPaginate" : false
			});
		});

	</script>
	<style type="text/css">
		.border {
			border: 1px solid gray;
		}
	</style> 
</head>
<body>

	<?php
	$table = "<table style='width: 80%;' class='border' id='parseTable'><thead><tr><th class='border'>Type</th><th class='border'>Value</th><th class='border'>Notes</th><th class='border'>id</th><th class='border'>Modified</th><th class='border'>Original</th></tr></thead><tbody>";

	while ($row = mysqli_fetch_array($q, MYSQLI_BOTH)) {
		// TRIM AND LOWERCASE NOTES
		$notes = strtolower(trim($row['notes']));

		$valueModified = '';

		// CONVERT STRING NUMBERS TO DIGITS
		$stringNumbers = array('1 hour' => 60, '1.5 hours' => 90, '1hr' => 60, '1 hr' => 60, '1.15' => 75, '1:15' => 75, '1/2 hr'=>30,'2hr' => 120, '2 hr' => 120,'130' => '90','115' => '75','145'=>'105','230'=>150);
		
		foreach ($stringNumbers as $key => $value) {
			$notes = str_replace($key, $value, $notes);
		}

		// FLAG IF VALUE HAS BEEN MODIFIED
		if ($notes !== strtolower(trim($row['notes']))){
			$valueModified = 'true';
		}

		// RULE OUT GARBAGE DATA
		// EMPTY CELLS
		$matchType = '';
		$value = '';
		if ($notes == '') {
			$matchType = "empty";
			$value = 'null';
		}
		// NO NUMBERS IN STRING
		elseif (preg_match('/^\D*$/', $notes)) {
			$matchType = "text-only";
			$value = 'null';
		}

		// ONLY ONE NUMBER IN STRING IN STRING  //NOTE- need to solve for 5min
		elseif (preg_match('/^\D*[0-9]\D*$/', $notes)) {
			$matchType = "single-digit";
			$value = 'null';
		}

		// MATCH TYPE TO 2 OR 3 DIGITS xx(x) WITH NO OTHER DIGITS
		elseif (preg_match('/^\D*[0-9]{2,3}\D*$/', $notes)) {
			$matchType = "2_or_3digits";
			$value = preg_replace('/\D/','',$notes);
		}

		// MATCH TYPE TO x(x)-xx EVEN IF OTHER NUMBERS ARE IN THE STRING. PICKS 2nd DIGIT  //NOTE - REPLACE DOESNT WORK IF THERE IS ANOTHER HYPHEN IN THE STRING
		elseif (preg_match('/(^.*\s+|^)[0-9]{1,2}-[0-9]{2}(\s+.*$|$)/', $notes)) {
			$matchType = "x(x)-xx";
			$hyphen = strstr($notes, '-');
			$value = substr($hyphen, 1, 2);
		}


		// MATCH TYPE TO x(xx)-xxx EVEN IF OTHER NUMBERS ARE IN THE STRING. PICKS 2nd DIGIT
		elseif (preg_match('/(^.*\s+\D*|^)[0-9]{1,3}-[0-9]{3}(\s+.*$|$)/', $notes)) {
			$matchType = "x(xx)-xxx";
			$hyphen = strstr($notes, '-');
			$value = substr($hyphen, 1, 3);
		}


		// MATCH TYPE TO x(x)/xx EVEN IF OTHER NUMBERS ARE IN THE STRING. PICKS 2nd DIGIT  //NOTE - REPLACE DOESNT WORK IF THERE IS ANOTHER HYPHEN IN THE STRING
		elseif (preg_match('/(^.*\s+\D*|^)[0-9]{1,2}\/[0-9]{2}(\s+.*$|$)/', $notes)) {
			$matchType = "x(x)/xx";
			$backslash = strstr($notes, '/');
			$value = substr($backslash, 1, 2);
		}


		// MATCH TYPE TO x(xx)/xxx EVEN IF OTHER NUMBERS ARE IN THE STRING. PICKS 2nd DIGIT
		elseif (preg_match('/(^.*\s+|^)[0-9]{1,3}\/[0-9]{3}(\s+.*$|$)/', $notes)) {
			$matchType = "x(xx)/xxx";
			$backslash = strstr($notes, '/');
			$value = substr($backslash, 1, 3);
		}


		// MATCH TYPE TO ANY RESERVATION TIMES x(x):xx
		elseif (preg_match('/^\D*[0-9]{1,2}:[0-9]{2}\D*$/', $notes)) {
			$matchType = "reservation time";
			$value = 'null';
		}

		// MATCH TYPE TO 2 DIGITS with MINUTES xx(x)  // NOTE: PROBABLY REDUNDANT
		elseif (preg_match('/^\D*[0-9]{2}\s*min\D*$/', $notes)) {
			$matchType = "2_Digits_w/_Min";
			$value = preg_replace('/\D/','',$notes);
		}

		// MATCH TYPE TO 3 DIGITS with MINUTES xx(x) // NOTE: PROBABLY REDUNDANT
		elseif (preg_match('/^\D*[0-9]{3}\s*min\D*$/', $notes)) {
			$matchType = "3_Digits_w/_Min";
			$value = preg_replace('/\D/','',$notes);
		}

		// NUMBER USED FOR ANOTHER REASON. NOUN AFTEWARD
		elseif (preg_match('/[0-9]+\s*(baby|adult|table|kid|tble|high|ppl|personas)/', $notes)) {
			$matchType = "#_not_a_time";
			$value = 'null';
		}

		// NUMBER USED FOR ANOTHER REASON. NOUN AFTEWARD
		elseif (preg_match('/(table|tble|tbl|tb)\s*[0-9]+/', $notes)) {
			$matchType = "#_not_a_time2";
			$value = 'null';
		}


		$original = ($valueModified)? strtolower(trim($row['notes'])): '';

		// FILL TABLE IN
		$table .= print_r("<tr><td>" . $matchType . "</td><td>" . $value . "</td><td>" . $notes . "</td><td>" . $row['id'] . "</td><td>" . $valueModified . "</td><td>" . $original . "</td></tr>", 1);
	}
	$table .= "</tbody></table>";

	echo $table;
?>
</body>
</html>
