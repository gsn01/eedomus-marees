<?php

// Donnees sur les caracteristiques des marees, recuperees sur les sites horaire-maree.fr pour la maree du jour, maree.info pour les grandes marees, d'après les donnees du SHOM, non verifiees
// Produit par G. SIMON v1 mars 2016
// Version 1.1
// 1.1 : ajout d'un test d'existence des ports demandes en parametres

// Voir la description des donnees produites en fin de script

// Fonctions
function sdk_multiexplode ($delimiters,$string) {	// Separer une chaine de caracteres suivant plusieurs separateurs
    
    $ready  = str_replace($delimiters, $delimiters[0], $string);
    $launch = explode($delimiters[0], $ready);
    return  $launch;
}
function sdk_format_hauteur ($chaine) {		// Formatage de la hauteur de maree en nuerique: ex : "<br /> 3,82 m" => "3.82"
	return str_replace(',','.',str_replace(array("<br /> "," m"),'',$chaine));
}

function sdk_est_superieur ($chaine1,$chaine2) {	// compare si les heures sont superieures dans des chaines formatees : "xhhHmm"
	$retour = 0;
//	echo "Chaines : $chaine1//$chaine2\n";
	$extrait1 = substr($chaine1,1,2).substr($chaine1,4,2);
	$extrait2 = substr($chaine2,1,2).substr($chaine2,4,2);
//	echo "Extraits : -$extrait1-//-$extrait2-";
	
	if ( $extrait1 > $extrait2) {$retour = 1; /* echo "$chaine1 > $chaine2";*/}

	return $retour;
}

function sdk_tri_tableau($tableau) {	// Tri d'un tableau algorithme "tri a bulles" d'apres Wikipedia
	//var_dump($tableau);
	$nb = count($tableau) - 2;
	while ( $nb >= 0 ) {
//		echo "nb=$nb\n";
		for ( $i=0; $i <= $nb; $i++) {
			if ( sdk_est_superieur($tableau[$i],$tableau[$i+1]) == 1) {
				// Permuter les positions i et i+1 du tableau
				$tmp = $tableau[$i];
				$tableau[$i] = $tableau[$i+1];
				$tableau[$i+1] = $tmp;
			}
		}
		$nb = $nb - 1;
	}
//	echo "Tableau trie :\n";
//	var_dump($tableau);
	return $tableau;
}

// ---------------------Debut -------------------------------------------
$port1 = getArg("port1"); // Ex : PERROS-GUIREC_TRESTRAOU, libelle a récuperer sur le site horaire-maree.fr apres choix du port dans la liste
$port2 = getArg("port2"); // Ex : 66, a récuperer sur le site maree.info apres choix du port dans la liste

/**********************************************************************/
/* Traiter la maree du jour (il y aussi celle des 10 prochains jours) */
/**********************************************************************/

$base_url = "http://www.horaire-maree.fr/";
$response_maree_jour = httpQuery($base_url."maree/".$port1."/", 'GET');

$exploded1 = sdk_multiexplode(array('<div id="i_donnesJour">','</div>'),$response_maree_jour); // Premier decoupage sur "<div id="i_donnesJour">" et "</div>"". Les donnees de la maree du jour sont en [1])

$exploded2 = sdk_multiexplode(array('<strong>','</strong>','<td class="blueoffice whitetxt">','</td>'),$exploded1[11]); // Second decoupage

// Recuperation des differentes donnees dans la page lue
$coeff_matin = $exploded2[18];
$maree_matin_BM = $exploded2[21];
$hauteur_matin_BM = sdk_format_hauteur($exploded2[22]);
$maree_matin_PM = $exploded2[24];
$hauteur_matin_PM = sdk_format_hauteur($exploded2[25]);
$coeff_apres_midi = $exploded2[27];
$maree_apres_midi_BM = $exploded2[29];
$hauteur_apres_midi_BM = sdk_format_hauteur($exploded2[30]);
$maree_apres_midi_PM = $exploded2[32];
$hauteur_apres_midi_PM = sdk_format_hauteur($exploded2[33]);
// echo $coeff_matin."\n".$maree_matin_BM."\n".$hauteur_matin_PM."\n".$maree_matin_PM."\n".$coeff_apres_midi."\n".$maree_apres_midi_BM."\n".$maree_apres_midi_PM."\n";

// Forme affichable, pas calculable, BMs en premier, puis PMs
$marees_txt = $coeff_matin." BM ".$maree_matin_BM." / ".$maree_apres_midi_BM." PM ".$maree_matin_PM." / ".$maree_apres_midi_PM;
$marees_txt = str_replace(' 0',' ', $marees_txt);			// Retrait des 0 non significatifs

// Classement des horaires journaliers par heure
$tableau_trie = sdk_tri_tableau(array("-".$maree_matin_BM, "-".$maree_apres_midi_BM,"+".$maree_matin_PM,"+".$maree_apres_midi_PM));

// Forme textuelle de la maree par ordre chronologique
$tableau_trie_2 = str_replace('+0','+', $tableau_trie);		// Retrait des 0 non significatifs
$tableau_trie_2 = str_replace('-0','-', $tableau_trie_2);		// Retrait des 0 non significatifs
$marees_chrono = $coeff_matin;
foreach ($tableau_trie_2 as $texte) $marees_chrono .= " ".$texte;

// Calcul du type de maree
if ($$coeff_matin <= 70) { $type_maree = 0 /* Mortes Eaux */; }
if ($coeff_matin > 70 and $coeff_matin < 100) {   $type_maree = 1 /* Vives Eaux */; }
if ($coeff_matin >= 100) { $type_maree = 2 /* Grande Maree */; }

// Calcul du sens de maree : montante ou descendante
// Algorithme : un peu tordu
// 	Constitution d'un tableau ou l'heure courante est inseree
//	On parcourt le tableau jusqu'à l'heure courante (reperee par un x)
//	Et on compare par rapport au type de maree de l'element precedent
$heure=date("H\hi");	// Heure courante au format xxhxx
$tableau_trie = sdk_tri_tableau(array_merge($tableau_trie,array("x".$heure)));	// Ajout de l'heure courante au tableau, et tri

for ($i=0 ; $i < count($tableau_trie); $i++) {	// Pour chaque ligne du tableau
	if (substr($tableau_trie[$i],0,1) == "x")  break;	// Arrêt sur la ligne prefixee par x (heure courante)
	}

// On regarde l'element precedent (ou le suivant si l'heure courante est en premier)
if ( $i <> 0 ) {
	if (substr($tableau_trie[$i-1],0,1) == "+" ) $sens_maree = "-1"; else $sens_maree = "1";
	}
	else {
	if (substr($tableau_trie[1],0,1) == "+") $sens_maree = "1"; else $sens_maree = "-1";
	};

// Test du résultat 1
// Si le port passe en parametre n'existe pas, le resultat n'inclut pas de hauteurs, ni horaires
if ($maree_matin_BM == "" and $maree_matin_PM == "" and $maree_apres_midi_BM == "" and $maree_apres_midi_PM == "") {
	echo "<erreur>Le port $port1 n'existe pas sur horaires-marees.fr</erreur>";
	die();
	}

/**********************************************************************/
/* Prochains coefficients de vives-eaux => pour trouver la prochaine grande maree dans le tableau recupere */
/**********************************************************************/
// Algorithme : parcourir tous les prochains jours de grande maree (>100) jusqu'a ce que le coefficient redescende

$response_grande_maree = httpQuery("maree.info/".$port2."/coefficients?c=gm", 'GET');

$exploded1 = sdk_multiexplode(array('<li>','</li>'),$response_grande_maree); // Premier decoupage sur balise 'li', on prendra une ligne sur deux

$tmp_coeff_max = 0;
for ($i = 1; $i <= 10; $i=$i+2) { // Max de 10 lignes, on saute un vide a chaque fois
//	echo "i=$i\nLigne unitaire à traiter : $exploded1[$i]\n";
	$exploded2 = sdk_multiexplode(array('- coefficients ','- coefficient ','<a '), $exploded1[$i]);	//
	if ( count($exploded2) == 1 ) break;	// Ne pas traiter si c'est une ligne sans coefficient (date ou autre)
	$tmp_coeff_matin=substr($exploded2[1],0,3);	// 3 premiers caracteres
	if ($tmp_coeff_matin > $tmp_coeff_max) {$tmp_coeff_max = $tmp_coeff_matin; $exploded2_precedent = $exploded2;} else break;	// Le test du MAX est fait sur le coefficient du matin
	}
$date_grande_maree = trim($exploded2_precedent[0]);
$coeff_grande_maree = trim($exploded2_precedent[1]);

// Test du résultat 2
// Si le port passe en parametre n'existe pas, le resultat n'inclut pas d'informations
if ($date_grande_maree == "") {
	echo "<erreur>Le port $port2 n'existe pas dans la base maree.info</erreur>";
	die();
	}


/**********************************************************************/
// Affichage des donnees au format XML
/**********************************************************************/

sdk_header('text/xml');

echo"<root>";

echo "<marees_txt>".$marees_txt."</marees_txt>";									// Description txt de la maree du jour, coeff du matin + heures de BM et PM
echo "<marees_chrono>".$marees_chrono."</marees_chrono>";							// Description txt de la maree du jour, classe chronologiquement. Ex : 
echo "<coeff_matin>".$coeff_matin."</coeff_matin>";									// Coefficient de la Pleine Mer du matin
echo "<maree_matin_BM>".$maree_matin_BM."</maree_matin_BM>";						// Heure de la Basse Mer du matin
echo "<maree_matin_BM_num>".str_replace('h','',$maree_matin_BM)."</maree_matin_BM_num>";						// Heure de la Basse Mer du matin
echo "<hauteur_matin_BM>".$hauteur_matin_BM."</hauteur_matin_BM>";					// ...
echo "<maree_matin_PM>".$maree_matin_PM."</maree_matin_PM>";						// ...
echo "<maree_matin_PM_num>".str_replace('h','',$maree_matin_PM)."</maree_matin_PM_num>";						// ...
echo "<hauteur_matin_PM>".$hauteur_matin_PM."</hauteur_matin_PM>";					// ...
echo "<coeff_apres_midi>".$coeff_apres_midi."</coeff_apres_midi>";					// ...
echo "<maree_apres_midi_BM>".$maree_apres_midi_BM."</maree_apres_midi_BM>";			// ...
echo "<maree_apres_midi_BM_num>".str_replace('h','',$maree_apres_midi_BM)."</maree_apres_midi_BM_num>";			// ...
echo "<hauteur_apres_midi_BM>".$hauteur_apres_midi_BM."</hauteur_apres_midi_BM>";	// ...
echo "<maree_apres_midi_PM>".$maree_apres_midi_PM."</maree_apres_midi_PM>";			// ...
echo "<maree_apres_midi_PM_num>".str_replace('h','',$maree_apres_midi_PM)."</maree_apres_midi_PM_num>";			// ...
echo "<hauteur_apres_midi_PM>".$hauteur_apres_midi_PM."</hauteur_apres_midi_PM>";	// ...
echo "<type_maree>".$type_maree."</type_maree>";									// 0=Mortes Eaux (<70), 1=Vives Eaux (>70), 2=Grande Marée (>100)
echo "<sens_maree>".$sens_maree."</sens_maree>";									// -1=Descendante, 1=Montante
echo "<date_grande_maree>".$date_grande_maree."</date_grande_maree>";				// Date de la prochaine grande maree (calcul base sur le cofficient du matin
echo "<coeff_grande_maree>".$coeff_grande_maree."</coeff_grande_maree>";			// Et le coefficient du matin qui va avec

echo "</root>";


?>
