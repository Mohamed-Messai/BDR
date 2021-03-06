<?php
include 'connexion.php';

/** DB schema name**/
function generateRandomString($length = 10) {
	$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$charactersLength = strlen($characters);
	$randomString = '';
	for ($i = 0; $i < $length; $i++) {
		$randomString .= $characters[rand(0, $charactersLength - 1)];
	}
	return $randomString;
}

/** Generic HTML printing **/
function printHtmlBegin ($schema) {
	echo '<html>';
	echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/></head>';
	echo '<title>DB Disco</title>';
	echo '<link href="css/main.css" type="text/css" rel="stylesheet"/>';
	echo '<body>';
	echo '<h1>DB Disco</h1>
		<i>Interface de découverte des bases de données relationnelles</i>';
	echo '<h2> Base de données : ' . $schema . '</h2>';
}

function printHtmlForm ($usersql='') {
	echo '<form action="index.php" method="post">';
	echo '<textarea name="usersql" cols="80" rows="20">' . $usersql . '</textarea><br>';
	echo '<input type="submit" value="Exécuter SQL">';
	echo '</form>';
}

function printHtmlEnd ($conn) {
	$st = $conn->prepare("SELECT LEFT(version(),17)");
	$st->execute();
	$res = $st->fetch(PDO::FETCH_NUM);
	$pgVersion = $res[0];
	echo '<footer>
		<hr/>
		<a href="index.php?deleteall=true"><i>Réinitialiser la BD courante<i></a>
		<hr/>
		<p>DB Disco utilise '.$pgVersion.' [<a href="https://www.postgresql.org/docs/">doc</a>] [<a href="http://librecours.net">cours</a>]
		<br/>Code source : <a href="https://gitlab.utc.fr/crozatst/dbdisco">https://gitlab.utc.fr/crozatst/dbdisco</a></p>
		<p><a rel="license" href="http://creativecommons.org/licenses/by-sa/4.0/"><img alt="Creative Commons License" style="border-width:0" src="https://i.creativecommons.org/l/by-sa/4.0/88x31.png" /></a>
		by <a href="https://stph.crzt.fr">Stéphane Crozat</a></p>
		';
	echo '</body>';
	echo '</html>';
}

/** User SQL execution **/
function execUserSql ($conn, $usersql) {
	if (strtoupper(substr(trim($usersql),0,6)) == 'SELECT') {
		printSelect($conn, $usersql, "", $usersql);
		echo '<hr>';
	}
	else {
		$res=$conn->exec($usersql);
		if ($res===false) {
			$err = $conn->errorInfo();
			echo '<i>' . $err[2] . '</i>';
		}
	}
}

/** DB Printing **/
function printBdContent($conn, $schema) {
	echo '<h3>Contenu de la base de données</h3>';
	$sql = "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname=LOWER('" . $schema . "')";
	foreach ($conn->query($sql) as $row) {
		printTable($conn, $row[0], $schema);
		echo '<br>';
	}
	$sql = "SELECT viewname, definition FROM pg_catalog.pg_views WHERE schemaname=LOWER('" . $schema . "')";
	foreach ($conn->query($sql) as $row) {
		printView($conn, $row[0], $row[1]);
		echo '<br>';
	}
}

function printTable($conn, $table, $schema) {

	echo '<table border="1">';
	echo '<caption><b>' . strtoupper($table) . '</b></caption>';

	/** Column headers **/
	$sql = "SELECT column_name AS name, data_type AS type, character_maximum_length AS max
			FROM information_schema.columns
			WHERE table_name = LOWER('" . $table . "')
			AND table_schema = LOWER('" . $schema . "')
			ORDER BY ordinal_position";

	$st = $conn->prepare($sql);
	$st->execute();
	$res = $st->fetchAll(PDO::FETCH_ASSOC);
	echo '<tr>';
	foreach ($res as $prop) {
		if ($prop['type']=='character varying') $prop['type'] = 'varchar';
		if ($prop['max']) $prop['type'] .= '(' . $prop['max'] . ')';
		echo '<th>' . $prop['name'] . ':' . $prop['type'] . '</th>';
	}

	/** Content **/
	$sql = "SELECT * FROM " . $table;
	$st = $conn->prepare($sql);
	$st->execute();
	$res = $st->fetchAll(PDO::FETCH_ASSOC);
	if ($res) {
		foreach ($res as $row) {
			echo '<tr>';
			foreach ($row as $cell) {
				echo '<td>' . $cell . '</td>';
			}
			echo '</tr>';
		}
	}

	/** Table end **/
	echo '</table>';

}

function printView($conn, $name, $sql) {
	printSelect($conn, $sql, $name, "[vue] ".$sql);
}

function printSelect($conn, $sql, $caption="", $summary="", $header=TRUE) {

	$st = $conn->prepare($sql);
	$st->execute();
	$resN = $st->fetchAll(PDO::FETCH_NUM);

	if ($resN) {
		echo '<table border="1">';
		echo '<caption><b>' . strtoupper($caption) . '</b></caption>';
		echo '<summary>' . $summary . '</summary>';
		/** Print column headers **/
		if ($header) {
			/** Problem : If there is several columns with the same name FETCH_ASSOC returns only a column per colum name, columns are lost
			 ** Solution : Fetching with FETCH_NUM ($resN) and naming with FETCH_ASSOC ($resA), and detecting case where columns are lost
			 **/
			$st->execute();
			$resA = $st->fetch(PDO::FETCH_ASSOC);
			$colA = array_keys($resA);
			$colN = array_keys($resN[0]);
			if (count($colA)==count($colN)) {
				echo '<tr>';
				foreach ($colA as $prop) {
					echo '<th>' . $prop . '</th>';
				}
				echo '</tr>';
			}
			else {
				echo "Plusieurs colonnes ont le même nom, je suis embêté pour afficher les entêtes de colonne...";
			}
		}
		/** Print values **/
		foreach ($resN as $row) {
			echo '<tr>';
			foreach ($row as $cell) {
				echo '<td>' . $cell . '</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}
	else {
		echo 'La requête ne renvoie aucun résultat<br/>';
		$err = $conn->errorInfo();
		echo '<i>' . $err[2] . '</i>';
	}
}
