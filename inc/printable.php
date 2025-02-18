<?php
require_once VTM_CHARACTER_URL . 'lib/fpdf.php';
require_once VTM_CHARACTER_URL . 'inc/classes.php';

/* read these from config options or use defaults */
$printtitle  = 'Character Sheet';
$footer      = 'plugin.gvlarp.com';
$printtitlefont   = 'Arial';
$printtitlecolour = array(0,0,0); /* RGB BB0506 */
$dividerlinecolor = array(187,05,06); /* RGB */
$dividertextcolor = array(187,05,06); /* RGB */
$dividerlinewidth = 0.5;

/* Not read from config options */
$printtitlesize     = 16;
$textfont      = 'Arial';
$textcolour    = array(0,0,0); /* RGB */
$textsize      = 9; /* points */
$textrowheight = 5; /* mm */
$margin        = 5;
$pagewidth     = 210;
$headsize      = 10;
$headrowheight = 10;
$dividertextsize  = 12;
$dividerrowheight = 9;
$overrun       = 18;

$dotmaximum = 5;  /* get this from character */
			
function vtm_print_redirect()
{
    if( is_page(vtm_get_stlink_page('printCharSheet')) )
    {
		$characterID = 0;
		if (is_user_logged_in()) {
			// Storytellers can look at any character
			if (vtm_isST() && isset($_REQUEST['characterID']))
				$characterID = $_REQUEST['characterID'];
			// Anyone can look at a character they have generated
			elseif(isset($_REQUEST['characterID'])) {
				$current_user = wp_get_current_user();
				$wordpressid = $current_user->user_login;
				if (vtm_get_chargen_wordpressid($_REQUEST['characterID']) == $wordpressid) {
					$characterID = $_REQUEST['characterID'];
				}
			}
			// If you haven't specified a character then use the one that links
			// to your login
			else {
				$character = vtm_establishCharacter('');
				$characterID = vtm_establishCharacterID($character);
			}
		} 
		elseif(isset($_REQUEST['characterID'])) {
			// You can only look at characters currently being generated
			// where the character isn't being generated by a logged in account
			if (vtm_get_chargen_wordpressid($_REQUEST['characterID']) == '') {
				$characterID = $_REQUEST['characterID'];
			}
		
		}
		
		if ($characterID > 0)
			vtm_render_printable($characterID);

    }
}
add_action( 'template_redirect', 'vtm_print_redirect' );

function vtm_get_chargen_wordpressid($characterID) {
	global $wpdb;
	
	return $wpdb->get_var($wpdb->prepare("SELECT WORDPRESS_ID FROM " . VTM_TABLE_PREFIX . "CHARACTER_GENERATION WHERE CHARACTER_ID = %s", $characterID));
}

function vtm_render_printable($characterID) {
	global $printtitle;
	global $margin;
	global $wpdb;
	global $textsize;
	global $textfont;
	global $textrowheight;
	global $vtmglobal;
	global $overrun;

	$mycharacter = new vtmclass_character();
	$mycharacter->load($characterID);
	
	if (class_exists('FPDF')) {
	
		$dotmaximum = $mycharacter->max_rating;

		$pdf = new vtmclass_PDFcsheet('P','mm','A4');
		$pdf->LoadOptions();
		$pdf->SetTitle($printtitle);
		$pdf->AliasNbPages();
		$pdf->SetMargins($margin, $margin , $margin);
		$pdf->AddPage();
		
		$pdf->BasicInfoTableRow(array(
				'Character Name:', $mycharacter->name,
				'Clan:', $mycharacter->private_clan,
				'Generation:',$mycharacter->generation,
				)
		);
		$pdf->BasicInfoTableRow(array(
				'Player Name:', $mycharacter->player,
				'Public Clan:', $mycharacter->clan,
				'Domain:', $mycharacter->domain,
				)
		);
		
		if ($vtmglobal['config']->USE_NATURE_DEMEANOUR == 'Y') {
			$pdf->BasicInfoTableRow(array(
					'Nature:', $mycharacter->nature,
					'Demeanour:', $mycharacter->demeanour,
					'Updated', date_i18n(get_option('date_format'),strtotime($mycharacter->last_updated)),
					)
			);

		}
		
		$pdf->Divider('Attributes');
		
		$physical = $mycharacter->getAttributes("Physical");
		$social   = $mycharacter->getAttributes("Social");
		$mental   = $mycharacter->getAttributes("Mental");
					
		for ($i=0;$i<3;$i++) {
		
			$physicalname = isset($physical[$i]->name)      ? $physical[$i]->name                    : '';
			$physicalspec = isset($physical[$i]->specialty) ? $physical[$i]->specialty : '';
			$physicallvl  = isset($physical[$i]->level)     ? $physical[$i]->level                   : 0;
			$physicalpnd  = isset($physical[$i]->pending)   ? $physical[$i]->pending                 : 0;
			$socialname   = isset($social[$i]->name)        ? $social[$i]->name                    : '';
			$socialspec   = isset($social[$i]->specialty)   ? $social[$i]->specialty : '';
			$sociallvl    = isset($social[$i]->level)       ? $social[$i]->level                   : 0;
			$socialpnd    = isset($social[$i]->pending)     ? $social[$i]->pending                 : 0;
			$mentalname   = isset($mental[$i]->name)        ? $mental[$i]->name                    : '';
			$mentalspec   = isset($mental[$i]->specialty)   ? $mental[$i]->specialty : '';
			$mentallvl    = isset($mental[$i]->level)       ? $mental[$i]->level                   : 0;
			$mentalpnd    = isset($mental[$i]->pending)     ? $mental[$i]->pending                 : 0;
		
			$data = array(
					$physicalname,$physicalspec,$physicallvl,$physicalpnd,
					$socialname,  $socialspec,  $sociallvl,  $socialpnd,
					$mentalname,  $mentalspec,  $mentallvl,  $mentalpnd
					);
			if ($i==0)
				array_push($data, "Physical", "Social", "Mental");
				
			$pdf->FullWidthTableRow($data);
		}
		
		$pdf->Divider('Abilities');

		$talent    = $mycharacter->getAbilities("Talents");
		$skill     = $mycharacter->getAbilities("Skills");
		$knowledge = $mycharacter->getAbilities("Knowledges");
		
		$abilrows = 3;
		if ($abilrows < count($talent)) $abilrows = count($talent);
		if ($abilrows < count($skill)) $abilrows = count($skill);
		if ($abilrows < count($knowledge)) $abilrows = count($knowledge);
		
		for ($i=0;$i<$abilrows;$i++) {
			$talentname    = isset($talent[$i]->skillname)    ? $talent[$i]->skillname               : '';
			$talentspec    = isset($talent[$i]->specialty)    ? $talent[$i]->specialty : '';
			$talentlvl     = isset($talent[$i]->level)        ? $talent[$i]->level                   : 0;
			$talentpnd     = isset($talent[$i]->pending)      ? $talent[$i]->pending                 : 0;
			$skillname     = isset($skill[$i]->skillname)     ? $skill[$i]->skillname                : '';
			$skillspec     = isset($skill[$i]->specialty)     ? $skill[$i]->specialty : '';
			$skilllvl      = isset($skill[$i]->level)         ? $skill[$i]->level                   : 0;
			$skillpnd      = isset($skill[$i]->pending)       ? $skill[$i]->pending                 : 0;
			$knowledgename = isset($knowledge[$i]->skillname) ? $knowledge[$i]->skillname               : '';
			$knowledgespec = isset($knowledge[$i]->specialty) ? $knowledge[$i]->specialty : '';
			$knowledgelvl  = isset($knowledge[$i]->level)     ? $knowledge[$i]->level                   : 0;
			$knowledgepnd  = isset($knowledge[$i]->pending)   ? $knowledge[$i]->pending                 : 0;
			$data = array(
					$talentname,   $talentspec,   $talentlvl,   $talentpnd,
					$skillname,    $skillspec,    $skilllvl,    $skillpnd,
					$knowledgename,$knowledgespec,$knowledgelvl,$knowledgepnd
					);
			if ($i==0)
				array_push($data, "Talents", "Skills", "Knowledges");
			$pdf->FullWidthTableRow($data);
		
		}
		
		$pdf->Divider();
		
		$backgrounds = $mycharacter->getBackgrounds();
		$disciplines = $mycharacter->getDisciplines();
		$primarypaths   = $mycharacter->primary_paths;
		$secondarypaths = $mycharacter->secondary_paths;
		
		$sql = "SELECT NAME, PARENT_ID FROM " . VTM_TABLE_PREFIX . "SKILL_TYPE;";
		$allgroups = $wpdb->get_results("$sql");	
		
		$secondarygroups = array();
		foreach ($allgroups as $group) {
			if ($group->PARENT_ID > 0)
				array_push($secondarygroups, $group->NAME);
		}	

		$secondary = array();
		foreach ($secondarygroups as $group)
				$secondary = array_merge($mycharacter->getAbilities($group), $secondary);	
		
		$alldisciplines = array();
		foreach ($disciplines as $discipline) {
			array_push($alldisciplines, array( $discipline->name , "", $discipline->level, $discipline->pending));
			if (isset($primarypaths[$discipline->name])) {
				foreach ($primarypaths[$discipline->name] as $path => $info) {
					array_push($alldisciplines, array( "", $path."(P)" , $info[0], $info[1]));
				}
			}
			if (isset($secondarypaths[$discipline->name])) {
				foreach ($secondarypaths[$discipline->name] as $path => $info) {
					array_push($alldisciplines, array( "", $path , $info[0], $info[1]));
				}
			}
		}
		
		$discrows = count($disciplines);
		if (count($primarypaths) > 0) {
			foreach ($primarypaths as $discipline => $majikpaths) {
				$discrows += count($majikpaths);
			}
			foreach ($secondarypaths as $discipline => $majikpaths) {
				$discrows += count($majikpaths);
			}
		}
		$loopoverrun = 6;
		$rows = 3;
		if ($rows < $discrows)           $rows = $discrows;
		if ($rows < count($backgrounds)) $rows = count($backgrounds);
		if ($rows < count($secondary))   $rows = count($secondary);
		
		for ($i=0;$i<$rows;$i++) {
			
		
			if (isset($backgrounds[$i]->sector))
				$sector = $backgrounds[$i]->sector;
			elseif (isset($backgrounds[$i]->comment))
				$sector = stripslashes($backgrounds[$i]->comment);
			else
				$sector = '';
				
			$is_discipline = isset($alldisciplines[$i]);
			$is_skill      = isset($secondary[$i]);
		
			$data = array (
				isset($backgrounds[$i]->background) ? $backgrounds[$i]->background : '',
				$sector,
				isset($backgrounds[$i]->level)      ? $backgrounds[$i]->level      : 0,
				isset($backgrounds[$i]->pending)    ? $backgrounds[$i]->pending    : 0,
				
				$is_discipline ? $alldisciplines[$i][0] : '',
				$is_discipline ? $alldisciplines[$i][1] : '',
				$is_discipline ? $alldisciplines[$i][2] : 0,
				$is_discipline ? $alldisciplines[$i][3] : 0,
				
				$is_skill ? $secondary[$i]->skillname : '',
				$is_skill ? $secondary[$i]->specialty : '',
				$is_skill ? $secondary[$i]->level : 0,
				$is_skill && isset($secondary[$i]->pending) ? $secondary[$i]->pending : 0
			);
			if ($i==0)
				array_push($data, "Backgrounds", "Disciplines", "Secondary Abilities");
				
			
			if ($i >= $loopoverrun && count($backgrounds) >= $loopoverrun && $i >= $discrows && $i >= count($secondary)) {
				$data = array (
					count($backgrounds) > $i ? "See Extended Backgrounds..." : '',
					'',
					'',
					'',
					
					'',
					'',
					'',
					'',
					
					'',
					'',
					'',
					'',
				);
				$loopoverrun = $overrun - $i;
				$pdf->FullWidthTableRow($data);
				break;
			} else {
				$pdf->FullWidthTableRow($data);
			}
		}

		
		$pdf->Divider();
		
		$ytop   = $pdf->GetY();
		$xstart = $pdf->GetX();
		/* COLUMN 1 */
		
			/* Merits and Flaws */
			$merits = $mycharacter->meritsandflaws;
			if (count($merits) > 0) {
				$xnext = $pdf->SingleColumnHeading("Merits and Flaws");
				
				$i = 0;
				foreach ($merits as $merit) {
					$string = $merit->name;
					if (!empty($merit->comment))
						$string .= " - " . $merit->comment;
					$string .= " (" . $merit->level . ")";
					if (isset($merit->pending) && $merit->pending != 0)
						$string .= " pending";
					$pdf->SingleColumnText($string);
					$i++;
					if ($i == $loopoverrun) {
						$pdf->SingleColumnText("See Extended Backgrounds for more...");
						break;
					}
				}
			} else {
				$xnext = $xstart;
			}
			
			//$xnext = $pdf->SingleColumnHeading('Current Experience');
			//$pdf->SingleColumnCell(($mycharacter->current_experience - $mycharacter->pending_experience) . " ( " . $mycharacter->spent_experience . " spent on character )");
			
			$ybottom = $pdf->GetY();
		
		/* COLUMN 2 */
			$pdf->SetXY($xnext, $ytop);
			
			/* Humanity */
			$x1 = $xnext;
			$pdf->SingleColumnHeading($mycharacter->path_of_enlightenment);
			$pdf->SetX($x1);
			$pdf->SingleColumnCell($mycharacter->path_rating);
			$pdf->SetX($x1);
			
			/* Willpower */
			$xnext = $pdf->Willpower($mycharacter->willpower,$mycharacter->pending_willpower,$mycharacter->current_willpower);
			
			/* Bloodpool */
			$pdf->SetX($x1);
			$pdf->BloodPool($mycharacter->bloodpool);
			
			if ($pdf->GetY() > $ybottom) $ybottom = $pdf->GetY();
		
		/* COLUMN 3 */
			/* Virtues */
			$pdf->SetXY($xnext, $ytop);
			$virtues = $mycharacter->getAttributes("Virtue");
			if (count($virtues) > 0)
				$pdf->SingleColumnTable("Virtues", $virtues);
			
			/* Health */
			$pdf->Health();
			
			if ($pdf->GetY() > $ybottom) $ybottom = $pdf->GetY();
		
		
		$pdf->SetXY($xstart, $ybottom);
		
		/* NEXT PAGE */
		$pdf->AddPage();
		
		/* Dates, Sire, Concept */
		$pdf->Divider('Character Information');
		$dob = explode('-',$mycharacter->date_of_birth);
		$doe = explode('-',$mycharacter->date_of_embrace);
		$doa = explode('-',$mycharacter->date_of_approval);
		$dobm = strftime("%b", strtotime($mycharacter->date_of_birth));
		$doem = strftime("%b", strtotime($mycharacter->date_of_embrace));
		$doam = strftime("%b", strtotime($mycharacter->date_of_approval));
		
		$pdf->BasicInfoTableRow( array(
				'Date of Birth',   ($dob[2] * 1) . " $dobm " . $dob[0],
				'Sire',          $mycharacter->sire,
				'Clan Flaw',     $mycharacter->clan_flaw,
			)
		);
		$pdf->BasicInfoTableRow( array(
				'Date of Embrace', ($doe[2] * 1) . " $doem " . $doe[0],
				'Sect',          $mycharacter->sect,
				'Site Login',    $mycharacter->wordpress_id,
			)
		);
		$pdf->BasicInfoTableRow( array(
				'Blood per round',    $mycharacter->blood_per_round,
				'Domain',         $mycharacter->domain,
				'Approval Date', ($doa[2] * 1) . " $doam " . $doa[0]
			)
		);
		$pdf->BasicInfoTableRow( array(
				'Current Experience',    $mycharacter->current_experience - $mycharacter->pending_experience,
				'Experience Spent',      $mycharacter->spent_experience,
				'', ''
			)
		);
		$pdf->Ln();
		if (!empty($mycharacter->concept)) {
			$pdf->FullWidthText('Character Concept','B');
			$pdf->FullWidthText($mycharacter->concept);
		}
		
		/* Rituals */
		$rituals = $mycharacter->rituals;
		if (count($rituals) > 0) {
			foreach ($rituals as $majikdiscipline => $rituallist) {
			$pdf->Divider($majikdiscipline . ' Rituals');
				foreach ($rituallist as $ritual) {
					$text = "(Level " . $ritual['level'] . ") " . $ritual['name'] . " - " . $ritual['description'];
					if ($ritual['pending'] > 0) $text .= " - PENDING";
					$pdf->FullWidthText($text);
				} 
			}
		}
		
		/* Combo Disciplines */
		$combodisciplines = $mycharacter->combo_disciplines;
		if (count($combodisciplines) > 0) {
			$pdf->Divider('Combo Disciplines');
			foreach ($combodisciplines as $discipline) {
				$pdf->FullWidthText($discipline);
			}
		}
		
		/* Extended backgrounds - backgrounds with full details */
		$pdf->Divider('Extended Backgrounds');
		if (count($backgrounds) > 0) {
			for ($i=0;$i<count($backgrounds);$i++) {
				if (!empty($backgrounds[$i]->sector) || !empty($backgrounds[$i]->comment) || !empty($backgrounds[$i]->detail)) {
					$text = $backgrounds[$i]->background . " " . $backgrounds[$i]->level;
					if (!empty($backgrounds[$i]->sector))  $text .= " (" . $backgrounds[$i]->sector . ")";
					if (!empty($backgrounds[$i]->comment)) $text .= " " . $backgrounds[$i]->comment;
				
					$pdf->FullWidthText($text, 'B');
					if (!empty($backgrounds[$i]->detail))  $pdf->FullWidthText($backgrounds[$i]->detail);
					$pdf->Ln($textrowheight/2);
				}
			}
		}
		
		/* Extended backgrounds - merits and flaws with full details */
		if (count($merits) > 0) {
			//$pdf->Divider('Extended Merits and Flaws');
			for ($i=0;$i<count($merits);$i++) {
				//if (!empty($merits[$i]->comment) || !empty($merits[$i]->detail)) {
				if (!empty($merits[$i]->detail)) {
					$text = $merits[$i]->name;
					if (!empty($merits[$i]->comment)) $text .= " (" . $merits[$i]->comment . ")";
				
					$pdf->FullWidthText($text, 'B');
					if (!empty($merits[$i]->detail))  $pdf->FullWidthText($merits[$i]->detail);
					$pdf->Ln($textrowheight/2);
				}
			}
		}
		
		// Extended backgrounds - history
		$history = $mycharacter->history;
		if (count($history) > 0) {
			//$pdf->Divider('Character History');
			for ($i=0;$i<count($history);$i++) {
				$text = $history[$i]->title;
				$pdf->FullWidthText($text, 'B');
				
				$pdf->FullWidthText($history[$i]->detail);
				$pdf->Ln($textrowheight/2);
			}
		}
		
		/* Output PDF */
		
		$pdf->Output();
		
	} else {
		echo "<p>Class error</p>";
		exit;
	}
}



class vtmclass_PDFcsheet extends FPDF
{

	function LoadOptions() {
		global $printtitle;
		global $footer;
		global $printtitlefont;
		global $printtitlecolour; 
		global $dividerlinecolor; /* RGB */
		global $dividertextcolor; /* RGB */
		global $dividerlinewidth;
		
		$printtitle     = get_option('vtm_pdf_title', 'Character Sheet');
		$printtitlefont = get_option('vtm_pdf_titlefont', 'Arial');
		$footer         = get_option('vtm_pdf_footer');
		$dividerlinewidth = get_option('vtm_pdf_divlinewidth', '1');
		
		$printtitlecolour = vtm_hex2rgb(get_option('vtm_pdf_titlecolour', '#000000'));
		$dividerlinecolor = vtm_hex2rgb(get_option('vtm_pdf_divcolour', '#000000'));
		$dividertextcolor = vtm_hex2rgb(get_option('vtm_pdf_divtextcolour', '#000000'));
	
	}

	function Header()
	{
		global $printtitle;
		global $printtitlefont;
		global $printtitlecolour;
		global $printtitlesize;

		$this->SetFont($printtitlefont,'B',$printtitlesize);
		$this->SetTextColor($printtitlecolour[0],$printtitlecolour[1],$printtitlecolour[2]);
		$this->Cell(0,10,$this->FormatString($printtitle),0,1,'C');

		$this->Ln(2);
	}
	/* Page footer */
	function Footer()
	{
		global $textcolour;
		global $footer;
		
		$footerdate = date_i18n(get_option('date_format'));
	
		$this->SetY(-15);
		$this->SetFont('Arial','I',8);
		$this->SetLineWidth(0.3);
		$this->SetTextColor($textcolour[0],$textcolour[1],$textcolour[2]);
		
		$this->Cell(0,10,$this->FormatString($footer . ' | Page ' . $this->PageNo().' of {nb} | Printed on ' . $footerdate),'T',0,'C');
	}
	
	function BasicInfoTableRow($data) {
	
		global $textcolour;
		global $textfont;
		global $textsize;
		global $textrowheight;
		global $dotmaximum;

		$numcols  = count($data);
		$colwidths = array(30,50,25,30,25,40);
		
		$this->SetTextColor($textcolour[0],$textcolour[1],$textcolour[2]);
		$this->SetDrawColor($textcolour[0],$textcolour[1],$textcolour[2]);
		$this->SetLineWidth(.3);
		
		for($i=0;$i<$numcols;$i=$i+2) {
			$this->SetFont($textfont,'B', $textsize);
			$this->Cell($colwidths[$i],$textrowheight,$this->FormatString($data[$i]),0,0,'R');
			
			$this->SetFont($textfont,'', $textsize);
			$this->Cell($colwidths[$i+1],$textrowheight,$this->FormatString($data[$i+1]),'B',0,'L'); 
		}
		
		$this->Ln();

	}
	
	function Divider($data = '') {
	
		global $textfont;
		global $margin;
		global $pagewidth;
		global $dividerlinecolor;
		global $dividertextcolor ;
		global $dividertextsize;
		global $dividerrowheight;
		global $dividerlinewidth;
	
		$padding = 7;
		$data = $this->FormatString($data);
		
		$this->Ln(1);
		
		$this->SetFont($textfont,'B', $dividertextsize);
		$this->SetTextColor($dividertextcolor[0],$dividertextcolor[1],$dividertextcolor[2]);
		$this->SetDrawColor($dividerlinecolor[0],$dividerlinecolor[1],$dividerlinecolor[2]);
		$this->SetLineWidth($dividerlinewidth);
		
		if ($data == '') {
			$this->Ln($dividerrowheight);
			$y = $this->GetY()-($dividerrowheight/2);
			$this->Line($margin,$y,$pagewidth - $margin,$y);	
		} else {
			$datawidth = $this->GetStringWidth($data);
			
			$this->Cell(0,$dividerrowheight,$data,0,1,'C');
			
			$y = $this->GetY()-($dividerrowheight/2);
			$x1 = $margin;
			$x2 = ($pagewidth / 2) - ($datawidth / 2) - $padding;
			$this->Line($x1,$y,$x2,$y);	
			
			$x1 = ($pagewidth / 2) + ($datawidth / 2) + $padding;
			$x2 = $pagewidth - $margin;
			$this->Line($x1,$y,$x2,$y);
		}
		
	}
	
	function FullWidthTableRow($data) {
	
		global $textcolour;
		global $textfont;
		global $margin;
		global $dotmaximum;
		global $pagewidth;
		global $textsize;
		global $textrowheight;
		global $headsize;

		
		$specialtysize = 7;
		$numcols   = 9;
		$itemwidth = 23;
		$specwidth = 23;
		$dotswidth = 20;
		
		$this->SetTextColor($textcolour[0],$textcolour[1],$textcolour[2]);
		
		if (count($data) > 12) {
			$this->SetFont($textfont,'B', $headsize);
			$this->Cell($itemwidth + $specwidth + $dotswidth,$textrowheight,$this->FormatString($data[12]),0, 0,'C');
			$this->Cell($itemwidth + $specwidth + $dotswidth,$textrowheight,$this->FormatString($data[13]),0, 0,'C');
			$this->Cell($itemwidth + $specwidth + $dotswidth,$textrowheight,$this->FormatString($data[14]),0, 0,'C');
			$this->Ln();
		}
		
		$this->SetDrawColor($textcolour[0],$textcolour[1],$textcolour[2]);
		$this->SetLineWidth(.3);
		
		for($i=0;$i<$numcols;$i=$i+4) {
			$data[$i+0] = $this->FormatString($data[$i+0]);
			$this->FitToCell($data[$i+0], $itemwidth, $textfont, '', $textsize); 
			$this->Cell($itemwidth,$textrowheight,$data[$i+0],0,  0,'L');
			
			$data[$i+1] = $this->FormatString($data[$i+1]);
			$this->FitToCell($data[$i+1], $specwidth, $textfont, 'I', $specialtysize); 
			$this->Cell($specwidth,$textrowheight,$data[$i+1],'B',0,'L');
			
			$data[$i+2] = $this->FormatString($data[$i+2]);
			$this->FitToCell($data[$i+2], $dotswidth, $textfont, '', $textsize); 
			/* $this->Cell($dotswidth,$textrowheight,$data[$i+2],0,  0,'L'); */
			$this->Dots($data[$i+2], $data[$i+3], $dotswidth, $this->GetX(), $this->GetY(), $dotmaximum, $textrowheight);
			$this->Cell($dotswidth,$textrowheight,'',0,  0,'L');
		}
		
		$this->Ln();
	
	}
	
	function FitToCell ($text, $cellwidth, $targetfont, $targetfmt, $targetfontsize) {
		$this->SetFont($targetfont,$targetfmt, $targetfontsize);
		$text = $this->FormatString($text);
		while ($cellwidth < $this->GetStringWidth($text) && $targetfontsize > 5) {
			$targetfontsize--;
			$this->SetFont($targetfont,$targetfmt, $targetfontsize);
		}
		
	}
	
	function Dots($level, $pending, $cellwidth, $xorig, $y, $max = 5, $rowheight, $dotheight = 0) {
	
		$padding = 0;
	
		if ($level > $max) $max = 10;
		if (empty($dotheight)) {
			$dotwidth = ($cellwidth - 2) / $max;
			$dotheight = $dotwidth;
		}
		else
			$dotwidth = 0;
			
		$yoffset = ($rowheight - $dotheight) / 2;
	
		for ($i=1;$i<=$max;$i++) {
			$x = $xorig + $padding + ($i - 1) * ($dotwidth ? $dotwidth : $dotheight);
			if ($i <= $level)
				$this->Image(VTM_CHARACTER_URL . "images/fulldot.jpg", $x, $y + $yoffset, $dotwidth, $dotheight);
			elseif ($pending > 0 && $i <= $pending)
				$this->Image(VTM_CHARACTER_URL . "images/pdfxpdot.jpg", $x, $y + $yoffset, $dotwidth, $dotheight);
			else
				$this->Image(VTM_CHARACTER_URL . "images/emptydot.jpg", $x, $y + $yoffset, $dotwidth, $dotheight);
		}
	}
	
	function BloodPool ($bloodpool) {
		global $pagewidth;
		global $margin;
		global $headsize;
		global $headrowheight;
		global $textfont;
		global $textcolour;
	
		$sectionwidth = ($pagewidth - 2 * $margin) / 3;
		$xorig = $this->GetX();
		
		$this->SetFont($textfont,'B',$headsize);
		$this->SetTextColor($textcolour[0],$textcolour[1],$textcolour[2]);
		$this->Cell($sectionwidth, $headrowheight, 'Bloodpool', 0, 1, 'C');

		$padding = 0;
	
		$boxwidth = ($sectionwidth - 2) / 10;
		$boxheight = $boxwidth;
	
		$x = $xorig + $padding;
		$xoffset = 0;
		$y = $this->GetY();
		for ($i=1;$i<=$bloodpool;$i++) {
			$this->Image(VTM_CHARACTER_URL . "images/box.jpg", $x + $xoffset, $y, $boxwidth, $boxheight);
			if ( ($i % 10) == 0) {
				$xoffset = 0;
				$y = $y + $boxheight;
			} else {
				$xoffset = ($i % 10) * $boxwidth;
			}
		}
			
		$this->SetXY($xorig, $y);
	
		return $this->GetY();
	}
	
	function Willpower ($max, $pending, $current = 0) {
		global $pagewidth;
		global $margin;
		global $headsize;
		global $headrowheight;
		global $textfont;
		global $textcolour;
	
		$sectionwidth = ($pagewidth - 2 * $margin) / 3;
		$dotwidth  = ($sectionwidth - 2) / 10;
		$dotheight = $dotwidth;
		$padding = 0;
		
		$x1 = $this->GetX();
		
		$this->SetFont($textfont,'B',$headsize);
		$this->SetTextColor($textcolour[0],$textcolour[1],$textcolour[2]);
		$this->Cell($sectionwidth, $headrowheight, 'Willpower', 0, 0, 'C');
		$xnext = $this->GetX();
		$this->Ln($headrowheight);
	
		/* Max WP - dots */
		$this->Dots($max, $pending, $sectionwidth, $x1, $this->GetY(), 10, $dotheight);
		
		$this->SetXY($x1, $this->GetY() + $dotheight + 2);
		
		/* Current WP - boxes */
		$xoffset = 0;
		$y = $this->GetY();
		for ($i=1;$i<=10;$i++) {
			if ($i > $current && $i <= $max)
				$this->Image(VTM_CHARACTER_URL . "images/boxcross2.jpg", $x1 + $padding + $xoffset, $y, $dotheight, $dotheight);
			else
				$this->Image(VTM_CHARACTER_URL . "images/box.jpg", $x1 + $padding + $xoffset, $y, $dotheight, $dotheight);
			$xoffset = $i * $dotheight;
		}
		

		$this->SetXY($x1, $y + $dotheight);
	
		return $xnext;
	}

	function SingleColumnHeading ($heading) {
		global $pagewidth;
		global $margin;
		global $headsize;
		global $headrowheight;
		global $textfont;
		global $textcolour;
	
		$sectionwidth = ($pagewidth - 2 * $margin) / 3;
	
		$this->SetTextColor($textcolour[0],$textcolour[1],$textcolour[2]);
		$this->SetFont($textfont,'B',$headsize);
		$this->Cell($sectionwidth, $headrowheight, $this->FormatString($heading), 0, 0, 'C');
		$nextcol = $this->GetX();
		$this->Ln();
		
		return $nextcol;
	}
	function SingleColumnCell ($text) {
		global $pagewidth;
		global $margin;
		global $textsize;
		global $textrowheight;
		global $textfont;
		global $textcolour;
		
		$cellpadding = 3;
	
		$sectionwidth = ($pagewidth - 2 * $margin) / 3;
	
		$this->SetTextColor($textcolour[0],$textcolour[1],$textcolour[2]);
		$this->SetDrawColor($textcolour[0],$textcolour[1],$textcolour[2]);
		$this->SetFont($textfont,'B',$textsize);
		$this->SetLineWidth(.3);
		$this->Cell($cellpadding,$textrowheight,'',0,0,'L');
		$this->Cell($sectionwidth - $cellpadding * 2, $textrowheight, $this->FormatString($text), 1, 1, 'C');
		
	}
	
	function SingleColumnText ($text) {
	
		global $pagewidth;
		global $margin;
		global $textsize;
		global $textrowheight;
		global $textfont;
		global $textcolour;
	
		$sectionwidth = ($pagewidth - 2 * $margin) / 3;
		$x1 = $this->GetX();
		
		$this->SetTextColor($textcolour[0],$textcolour[1],$textcolour[2]);
		$this->SetFont($textfont,'',$textsize);
		$this->WriteWordWrap($text, $sectionwidth, $textrowheight);
		$this->Ln();
		
	}
	function FullWidthText ($text, $textweight = '' ) {
	
		global $pagewidth;
		global $margin;
		global $textsize;
		global $textrowheight;
		global $textfont;
		global $textcolour;
	
		$sectionwidth = $pagewidth - 2 * $margin;
		$x1 = $this->GetX();
		
		$this->SetTextColor($textcolour[0],$textcolour[1],$textcolour[2]);
		$this->SetFont($textfont,$textweight,$textsize);
		$this->Write($textrowheight, $this->FormatString($text));
		$this->Ln();
		
	}
	function SingleColumnTable ($heading, $data) {
	
		global $pagewidth;
		global $margin;
		global $textsize;
		global $textrowheight;
		global $textfont;
		global $textcolour;
	
		$colwidths = array(23, 23, 20);
		$sectionwidth = ($pagewidth - 2 * $margin) / 3;
		$x1 = $this->GetX();
		
		$xnext = $this->SingleColumnHeading($this->FormatString($heading));
	
		$this->SetTextColor($textcolour[0],$textcolour[1],$textcolour[2]);
		$this->SetFont($textfont,'B',$textsize);
		$this->SetDrawColor($textcolour[0],$textcolour[1],$textcolour[2]);
		$this->SetLineWidth(.3);
		
		$this->SetX($x1);
		
		foreach ($data as $item) {
			$this->Cell($colwidths[0], $textrowheight, $this->FormatString($item->name),  0,   0, 'L');
			$this->Cell($colwidths[1], $textrowheight, ''         ,  'B', 0, 'L');
			$pending = isset($item->pending) ? $item->pending : 0;
			$this->Dots($item->level, $pending, $colwidths[2], $this->GetX(),$this->GetY(),5, $textrowheight);
			$this->Ln();
			$this->SetX($x1);
		}
		
		return $xnext;
	}
	
	function Health() {
		global $pagewidth;
		global $margin;
		global $textcolour;
		global $textfont;
		global $textsize;
	
		$x1 = $this->GetX();
		$xnext = $this->SingleColumnHeading('Health');
		$colwidths = array(26, 20, 20);
		$sectionwidth = ($pagewidth - 2 * $margin) / 3;
		$boxwidth = ($sectionwidth - 2) / 10;
		$boxheight = $boxwidth;
		
		$this->SetTextColor($textcolour[0],$textcolour[1],$textcolour[2]);
		$this->SetFont($textfont,'',$textsize);
		
		$data = array( 	array('Bruised', ''),
						array('Hurt',	'(-1)'),
						array('Injured','(-1)'),
						array('Wounded','(-2)'),
						array('Mauled',	'(-2)'),
						array('Crippled','(-5)'),
						array('Incapacitated','')
		);
		
		foreach ($data as $item) {
			$this->SetX($x1);
			$this->Cell($colwidths[0], $boxheight, $item[0],  0,   0, 'R');
			$this->Cell($colwidths[1], $boxheight, $item[1],  0,   0, 'C');
			$this->Image(VTM_CHARACTER_URL . "images/box.jpg", $this->GetX(), $this->GetY(), 0, $boxheight);
			$this->Ln();
		}

	
		return $xnext;
	}
	
	function WriteWordWrap ($text, $columnwidth, $rowheight) {
	
		//$text .= "($columnwidth / " . $this->GetStringWidth($text) . " / " .strlen($text) . ")";
		$text = $this->FormatString($text);
		
		if ($columnwidth < $this->GetStringWidth($text)) {
			$approxcharwidth = $this->GetStringWidth($text) / strlen($text);
			$approxwrapwidth = (int) ($columnwidth - 1) / $approxcharwidth;
			
			$text = wordwrap($text, $approxwrapwidth, "\n", true);
		}
		
		$this->Write($rowheight, $text);
	}
	
	function FormatString($text) {
		return stripslashes($text);
	}
}


?>