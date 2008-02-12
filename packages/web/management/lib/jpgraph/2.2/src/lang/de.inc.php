<?php
/*=======================================================================
// File: 	DE.INC.PHP
// Description: German language file for error messages
// Created: 	2006-03-06
// Author:	Timo Leopold (timo@leopold-hh.de)
// Ver:		$Id: de.inc.php 839 2007-01-22 21:02:04Z ljp $
//
// Copyright (c)
//========================================================================
*/

// Notiz: Das Format fuer jede Fehlermeldung ist array(<Fehlermeldung>,<Anzahl der Argumente>)
$_jpg_messages = array(

/*
** Headers wurden bereits gesendet - Fehler. Dies wird als HTML formatiert, weil es direkt als text zurueckgesendet wird
*/
10  => array('<table border="1"><tr><td style="color:darkred;font-size:1.2em;"><b>JpGraph Fehler:</b>
HTTP header wurden bereits gesendet.<br>Fehler in der Datei <b>%s</b> in der Zeile <b>%d</b>.</td></tr><tr><td><b>Erkl�rung:</b><br>HTTP header wurden bereits zum Browser gesendet, wobei die Daten als Text gekennzeichnet wurden, bevor die Bibliothek die Chance hatte, seinen Bild-HTTP-Header zum Browser zu schicken. Dies verhindert, dass die Bibliothek Bilddaten zum Browser schicken kann (weil sie vom Browser als Text interpretiert w�rden und daher nur Mist dargestellt w�rde).<p>Wahrscheinlich steht Text im Skript bevor <i>Graph::Stroke()</i> aufgerufen wird. Wenn dieser Text zum Browser gesendet wird, nimmt dieser an, dass die gesamten Daten aus Text bestehen. Such nach irgendwelchem Text, auch nach Leerzeichen und Zeilenumbr�chen, die eventuell bereits zum Browser gesendet wurden. <p>Zum Beispiel ist ein oft auftretender Fehler, eine Leerzeile am Anfang der Datei oder vor <i>Graph::Stroke()</i> zu lassen."<b>&lt;?php</b>".</td></tr></table>',2),

/*
** Setup Fehler
*/
11 => array('Es wurde kein Pfad f�r CACHE_DIR angegeben. Bitte gib einen Pfad CACHE_DIR in der Datei jpg-config.inc an.',0),
12 => array('Es wurde kein Pfad f�r TTF_DIR angegeben und der Pfad kann nicht automatisch ermittelt werden. Bitte gib den Pfad in der Datei jpg-config.inc an.',0),
13 => array('The installed PHP version (%s) is not compatible with this release of the library. The library requires at least PHP version %s',2),

/*
**  jpgraph_bar
*/

2001 => array('Die Anzahl der Farben ist nicht gleich der Anzahl der Vorlagen in BarPlot::SetPattern().',0),
2002 => array('Unbekannte Vorlage im Aufruf von BarPlot::SetPattern().',0),
2003 => array('Anzahl der X- und Y-Koordinaten sind nicht identisch. Anzahl der X-Koordinaten: %d; Anzahl der Y-Koordinaten: %d.',2),
2004 => array('Alle Werte f�r ein Balkendiagramm (barplot) m�ssen numerisch sein. Du hast den Wert nr [%d] == %s angegeben.',2),
2005 => array('Du hast einen leeren Vektor f�r die Schattierungsfarben im Balkendiagramm (barplot) angegeben.',0),
2006 => array('Unbekannte Position f�r die Werte der Balken: %s.',1),
2007 => array('Kann GroupBarPlot nicht aus einem leeren Vektor erzeugen.',0),
2008 => array('GroupBarPlot Element nbr %d wurde nicht definiert oder ist leer.',0),
2009 => array('Eins der Objekte, das an GroupBar weitergegeben wurde ist kein Balkendiagramm (BarPlot). Versichere Dich, dass Du den GroupBarPlot aus einem Vektor von Balkendiagrammen (barplot) oder AccBarPlot-Objekten erzeugst. (Class = %s)',1),
2010 => array('Kann AccBarPlot nicht aus einem leeren Vektor erzeugen.',0),
2011 => array('AccBarPlot-Element nbr %d wurde nicht definiert oder ist leer.',1),
2012 => array('Eins der Objekte, das an AccBar weitergegeben wurde ist kein Balkendiagramm (barplot). Versichere Dich, dass Du den AccBar-Plot aus einem Vektor von Balkendiagrammen (barplot) erzeugst. (Class=%s)',1),
2013 => array('Du hast einen leeren Vektor f�r die Schattierungsfarben im Balkendiagramm (barplot) angegeben.',0),
2014 => array('Die Anzahl der Datenpunkte jeder Datenreihe in AccBarPlot muss gleich sein.',0),


/*
**  jpgraph_date
*/

3001 => array('Es ist nur m�glich, entweder SetDateAlign() oder SetTimeAlign() zu benutzen, nicht beides!',0),

/*
**  jpgraph_error
*/

4002 => array('Fehler bei den Eingabedaten von LineErrorPlot. Die Anzahl der Datenpunkte mus ein Mehrfaches von drei sein!',0),

/*
**  jpgraph_flags
*/

5001 => array('Unbekannte Flaggen-Gr��e (%d).',1),
5002 => array('Der Flaggen-Index %s existiert nicht.',1),
5003 => array('Es wurde eine ung�ltige Ordnungszahl (%d) f�r den Flaggen-Index angegeben.',1),
5004 => array('Der Landesname %s hat kein korrespondierendes Flaggenbild. Die Flagge mag existieren, abr eventuell unter einem anderen Namen, z.B. versuche "united states" statt "usa".',1),


/*
**  jpgraph_gantt
*/

6001 => array('Interner Fehler. Die H�he f�r ActivityTitles ist < 0.',0),
6002 => array('Es d�rfen keine negativen Werte f�r die Gantt-Diagramm-Dimensionen angegeben werden. Verwende 0, wenn die Dimensionen automatisch ermittelt werden sollen.',0),
6003 => array('Ung�ltiges Format f�r den Bedingungs-Parameter bei Index=%d in CreateSimple(). Der Parameter muss bei index 0 starten und Vektoren in der Form (Row,Constrain-To,Constrain-Type) enthalten.',1),
6004 => array('Ung�ltiges Format f�r den Fortschritts-Parameter bei Index=%d in CreateSimple(). Der Parameter muss bei Index 0 starten und Vektoren in der Form (Row,Progress) enthalten.',1),
6005 => array('SetScale() ist nicht sinnvoll bei Gantt-Diagrammen.',0),
6006 => array('Das Gantt-Diagramm kann nicht automatisch skaliert werden. Es existieren keine Aktivit�ten mit Termin. [GetBarMinMax() start >= n]',0),
6007 => array('Plausibilt�tspr�fung f�r die automatische Gantt-Diagramm-Gr��e schlug fehl. Entweder die Breite (=%d) oder die H�he (=%d) ist gr��er als MAX_GANTTIMG_SIZE. Dies kann m�glicherweise durch einen falschen Wert bei einer Aktivit�t hervorgerufen worden sein.',2),
6008 => array('Du hast eine Bedingung angegeben von Reihe=%d bis Reihe=%d, die keine Aktivit�t hat.',2),
6009 => array('Unbekannter Bedingungstyp von Reihe=%d bis Reihe=%d',2),
6010 => array('Ung�ltiger Icon-Index f�r das eingebaute Gantt-Icon [%d]',1),
6011 => array('Argument f�r IconImage muss entweder ein String oder ein Integer sein.',0),
6012 => array('Unbekannter Typ bei der Gantt-Objekt-Title-Definition.',0),
6015 => array('Ung�ltige vertikale Position %d',1),
6016 => array('Der eingegebene Datums-String (%s) f�r eine Gantt-Aktivit�t kann nicht interpretiert werden. Versichere Dich, dass es ein g�ltiger Datumsstring ist, z.B. 2005-04-23 13:30',1),
6017 => array('Unbekannter Datumstyp in GanttScale (%s).',1),
6018 => array('Intervall f�r Minuten muss ein gerader Teiler einer Stunde sein, z.B. 1,5,10,12,15,20,30, etc. Du hast ein Intervall von %d Minuten angegeben.',1),
6019 => array('Die vorhandene Breite (%d) f�r die Minuten ist zu klein, um angezeigt zu werden. Bitte benutze die automatische Gr��enermittlung oder vergr��ere die Breite des Diagramms.',1),
6020 => array('Das Intervall f�r die Stunden muss ein gerader Teiler eines Tages sein, z.B. 0:30, 1:00, 1:30, 4:00, etc. Du hast ein Intervall von %d eingegeben.',1),
6021 => array('Unbekanntes Format f�r die Woche.',0),
6022 => array('Die Gantt-Skala wurde nicht eingegeben.',0),
6023 => array('Wenn Du sowohl Stunden als auch Minuten anzeigen lassen willst, muss das Stunden-Interval gleich 1 sein (anderenfalls ist es nicht sinnvoll, Minuten anzeigen zu lassen).',0),
6024 => array('Das CSIM-Ziel muss als String angegeben werden. Der Start des Ziels ist: %d',1),
6025 => array('Der CSIM-Alt-Text muss als String angegeben werden. Der Beginn des Alt-Textes ist: %d',1),
6027 => array('Der Fortschrittswert muss im Bereich [0, 1] liegen.',0),
6028 => array('Die eingegebene H�he (%d) f�r GanttBar ist nicht im zul�ssigen Bereich.',1),
6029 => array('Der Offset f�r die vertikale Linie muss im Bereich [0,1] sein.',0),
6030 => array('Unbekannte Pfeilrichtung f�r eine Verbindung.',0),
6031 => array('Unbekannter Pfeiltyp f�r eine Verbindung.',0),
6032 => array('Interner Fehler: Unbekannter Pfadtyp (=%d) f�r eine Verbindung.',1),

/*
**  jpgraph_gradient
*/

7001 => array('Unbekannter Gradiententyp (=%d).',1),

/*
**  jpgraph_iconplot
*/

8001 => array('Der Mix-Wert f�r das Icon muss zwischen 0 und 100 sein.',0),
8002 => array('Die Ankerposition f�r Icons muss entweder "top", "bottom", "left", "right" oder "center" sein.',0),
8003 => array('Es ist nicht m�glich, gleichzeitig ein Bild und eine Landesflagge f�r dasselbe Icon zu definieren',0),
8004 => array('Wenn Du Landesflaggen benutzen willst, musst Du die Datei "jpgraph_flags.php" hinzuf�gen (per include).',0),

/*
**  jpgraph_imgtrans
*/

9001 => array('Der Wert f�r die Bildtransformation ist au�erhalb des zul�ssigen Bereichs. Der verschwindende Punkt am Horizont muss als Wert zwischen 0 und 1 angegeben werden.',0),

/*
**  jpgraph_lineplot
*/

10001 => array('Die Methode LinePlot::SetFilled() sollte nicht mehr benutzt werden. Benutze lieber SetFillColor()',0),
10002 => array('Der Plot ist zu kompliziert f�r FastLineStroke. Benutze lieber den StandardStroke()',0),

/*
**  jpgraph_log
*/

11001 => array('Deine Daten enthalten nicht-numerische Werte.',0),
11002 => array('Negative Werte k�nnen nicht f�r logarithmische Achsen verwendet werden.',0),
11003 => array('Deine Daten enthalten nicht-numerische Werte.',0),
11004 => array('Skalierungsfehler f�r die logarithmische Achse. Es gibt ein Problem mit den Daten der Achse. Der gr��te Wert muss gr��er sein als Null. Es ist mathematisch nicht m�glich, einen Wert gleich Null in der Skala zu haben.',0),
11005 => array('Das Tick-Intervall f�r die logarithmische Achse ist nicht definiert. L�sche jeden Aufruf von SetTextLabelStart() oder SetTextTickInterval() bei der logarithmischen Achse.',0),

/*
**  jpgraph_mgraph
*/

12001 => array("Du benutzt GD 2.x und versuchst ein Nicht-Truecolor-Bild als Hintergrundbild zu benutzen. Um Hintergrundbilder mit GD 2.x zu benutzen, ist es notwendig Truecolor zu aktivieren, indem die USE_TRUECOLOR-Konstante auf TRUE gesetzt wird. Wegen eines Bugs in GD 2.0.1 ist die Qualit�t der Truetype-Schriften sehr schlecht, wenn man Truetype-Schriften mit Truecolor-Bildern verwendet.",0),
12002 => array('Ung�ltiger Dateiname f�r MGraph::SetBackgroundImage() : %s. Die Datei muss eine g�ltige Dateierweiterung haben (jpg,gif,png), wenn die automatische Typerkennung verwendet wird.',1),
12003 => array('Unbekannte Dateierweiterung (%s) in MGraph::SetBackgroundImage() f�r Dateiname: %s',2),
12004 => array('Das Bildformat des Hintergrundbildes (%s) wird von Deiner System-Konfiguration nicht unterst�tzt. ',1),
12005 => array('Das Hintergrundbild kann nicht gelesen werden: %s',1),
12006 => array('Es wurden ung�ltige Gr��en f�r Breite oder H�he beim Erstellen des Bildes angegeben, (Breite=%d, H�he=%d)',2),
12007 => array('Das Argument f�r MGraph::Add() ist nicht g�ltig f�r GD.',0),
12008 => array('Deine PHP- (und GD-lib-) Installation scheint keine bekannten Bildformate zu unterst�tzen.',0),
12009 => array('Deine PHP-Installation unterst�tzt das gew�hlte Bildformat nicht: %s',1),
12010 => array('Es konnte kein Bild als Datei %s erzeugt werden. �berpr�fe, ob Du die entsprechenden Schreibrechte im aktuellen Verzeichnis hast.',1),
12011 => array('Es konnte kein Truecolor-Bild erzeugt werden. �berpr�fe, ob Du wirklich die GD2-Bibliothek installiert hast.',0),
12012 => array('Es konnte kein Bild erzeugt werden. �berpr�fe, ob Du wirklich die GD2-Bibliothek installiert hast.',0),

/*
**  jpgraph_pie3d
*/

14001 => array('Pie3D::ShowBorder(). Missbilligte Funktion. Benutze Pie3D::SetEdge(), um die Ecken der Tortenst�cke zu kontrollieren.',0),
14002 => array('PiePlot3D::SetAngle() 3D-Torten-Projektionswinkel muss zwischen 5 und 85 Grad sein.',0),
14003 => array('Interne Festlegung schlug fehl. Pie3D::Pie3DSlice',0),
14004 => array('Tortenst�ck-Startwinkel muss zwischen 0 und 360 Grad sein.',0),
14005 => array('Pie3D Interner Fehler: Versuch, zweimal zu umh�llen bei der Suche nach dem Startindex.',0,),
14006 => array('Pie3D Interner Fehler: Z-Sortier-Algorithmus f�r 3D-Tortendiagramme funktioniert nicht einwandfrei (2). Versuch, zweimal zu umh�llen beim Erstellen des Bildes.',0),
14007 => array('Die Breite f�r das 3D-Tortendiagramm ist 0. Gib eine Breite > 0 an.',0),

/*
**  jpgraph_pie
*/

15001 => array('PiePLot::SetTheme() Unbekannter Stil: %s',1),
15002 => array('Argument f�r PiePlot::ExplodeSlice() muss ein Integer-Wert sein',0),
15003 => array('Argument f�r PiePlot::Explode() muss ein Vektor mit Integer-Werten sein.',0),
15004 => array('Tortenst�ck-Startwinkel muss zwischen 0 und 360 Grad sein.',0),
15005 => array('PiePlot::SetFont() sollte nicht mehr verwendet werden. Benutze stattdessen PiePlot->value->SetFont().',0),
15006 => array('PiePlot::SetSize() Radius f�r Tortendiagramm muss entweder als Bruch [0, 0.5] der Bildgr��e oder als Absoluwert in Pixel im Bereich [10, 1000] angegeben werden.',0),
15007 => array('PiePlot::SetFontColor() sollte nicht mehr verwendet werden. Benutze stattdessen PiePlot->value->SetColor()..',0),
15008 => array('PiePlot::SetLabelType() der Typ f�r Tortendiagramme muss entweder 0 or 1 sein (nicht %d).',1),
15009 => array('Ung�ltiges Tortendiagramm. Die Summe aller Daten ist Null.',0),
15010 => array('Die Summe aller Daten ist Null.',0),
15011 => array('Um Bildtransformationen benutzen zu k�nnen, muss die Datei jpgraph_imgtrans.php eingef�gt werden (per include).',0),

/*
**  jpgraph_plotband
*/

16001 => array('Die Dichte f�r das Pattern muss zwischen 1 und 100 sein. (Du hast %f eingegeben)',1),
16002 => array('Es wurde keine Position f�r das Pattern angegeben.',0),
16003 => array('Unbekannte Pattern-Definition (%d)',0),
16004 => array('Der Mindeswert f�r das PlotBand ist gr��er als der Maximalwert. Bitte korrigiere dies!',0),


/*
**  jpgraph_polar
*/

17001 => array('PolarPlots m�ssen eine gerade Anzahl von Datenpunkten haben. Jeder Datenpunkt ist ein Tupel (Winkel, Radius).',0),
17002 => array('Unbekannte Ausrichtung f�r X-Achsen-Titel. (%s)',1),
//17003 => array('Set90AndMargin() wird f�r PolarGraph nicht unterst�tzt.',0),
17004 => array('Unbekannter Achsentyp f�r PolarGraph. Er muss entweder \'lin\' oder \'log\' sein.',0),

/*
**  jpgraph_radar
*/

18001 => array('ClientSideImageMaps werden f�r RadarPlots nicht unterst�tzt.',0),
18002 => array('RadarGraph::SupressTickMarks() sollte nicht mehr verwendet werden. Benutze stattdessen HideTickMarks().',0),
18003 => array('Ung�ltiger Achsentyp f�r RadarPlot (%s). Er muss entweder \'lin\' oder \'log\' sein.',1),
18004 => array('Die RadarPlot-Gr��e muss zwischen 0.1 und 1 sein. (Dein Wert=%f)',1),
18005 => array('RadarPlot: nicht unterst�tzte Tick-Dichte: %d',1),
18006 => array('Minimum Daten %f (RadarPlots sollten nur verwendet werden, wenn alle Datenpunkte einen Wert > 0 haben).',1),
18007 => array('Die Anzahl der Titel entspricht nicht der Anzahl der Datenpunkte.',0),
18008 => array('Jeder RadarPlot muss die gleiche Anzahl von Datenpunkten haben.',0),

/*
**  jpgraph_regstat
*/

19001 => array('Spline: Anzahl der X- und Y-Koordinaten muss gleich sein.',0),
19002 => array('Ung�ltige Dateneingabe f�r Spline. Zwei oder mehr aufeinanderfolgende X-Werte sind identisch. Jeder eigegebene X-Wert muss unterschiedlich sein, weil vom mathematischen Standpunkt ein Eins-zu-Eins-Mapping vorliegen muss, d.h. jeder X-Wert korrespondiert mit exakt einem Y-Wert.',0),
19003 => array('Bezier: Anzahl der X- und Y-Koordinaten muss gleich sein.',0),

/*
**  jpgraph_scatter
*/

20001 => array('Fieldplots m�ssen die gleiche Anzahl von X und Y Datenpunkten haben.',0),
20002 => array('Bei Fieldplots muss ein Winkel f�r jeden X und Y Datenpunkt angegeben werden.',0),
20003 => array('Scatterplots m�ssen die gleiche Anzahl von X- und Y-Datenpunkten haben.',0),

/*
**  jpgraph_stock
*/

21001 => array('Die Anzahl der Datenwerte f�r Stock-Charts m�ssen ein Mehrfaches von %d Datenpunkten sein.',1),

/*
**  jpgraph_plotmark
*/

23001 => array('Der Marker "%s" existiert nicht in der Farbe: %d',2),
23002 => array('Der Farb-Index ist zu hoch f�r den Marker "%s"',1),
23003 => array('Ein Dateiname muss angegeben werden, wenn Du den Marker-Typ auf MARK_IMG setzt.',0),

/*
**  jpgraph_utils
*/

24001 => array('FuncGenerator : Keine Funktion definiert. ',0),
24002 => array('FuncGenerator : Syntax-Fehler in der Funktionsdefinition ',0),
24003 => array('DateScaleUtils: Unknown tick type specified in call to GetTicks()',0),

/*
**  jpgraph
*/

25001 => array('Diese PHP-Installation ist nicht mit der GD-Bibliothek kompiliert. Bitte kompiliere PHP mit GD-Unterst�tzung neu, damit JpGraph funktioniert. (Weder die Funktion imagetypes() noch imagecreatefromstring() existiert!)',0),
25002 => array('Diese PHP-Installation scheint nicht die ben�tigte GD-Bibliothek zu unterst�tzen. Bitte schau in der PHP-Dokumentation nach, wie man die GD-Bibliothek installiert und aktiviert.',0),
25003 => array('Genereller PHP Fehler : Bei %s:%d : %s',3),
25004 => array('Genereller PHP Fehler : %s ',1),
25005 => array('PHP_SELF, die PHP-Global-Variable kann nicht ermittelt werden. PHP kann nicht von der Kommandozeile gestartet werden, wenn der Cache oder die Bilddateien automatisch benannt werden sollen.',0),
25006 => array('Die Benutzung der FF_CHINESE (FF_BIG5) Schriftfamilie ben�tigt die iconv() Funktion in Deiner PHP-Konfiguration. Dies wird nicht defaultm��ig in PHP kompiliert (ben�tigt "--width-iconv" bei der Konfiguration).',0),
25007 => array('Du versuchst das lokale (%s) zu verwenden, was von Deiner PHP-Installation nicht unterst�tzt wird. Hinweis: Benutze \'\', um das defaultm��ige Lokale f�r diese geographische Region festzulegen.',1),
25008 => array('Die Bild-Breite und H�he in Graph::Graph() m�ssen numerisch sein',0),
25009 => array('Die Skalierung der Achsen muss angegeben werden mit Graph::SetScale()',0),

25010 => array('Graph::Add() Du hast versucht, einen leeren Plot zum Graph hinzuzuf�gen.',0),
25011 => array('Graph::AddY2() Du hast versucht, einen leeren Plot zum Graph hinzuzuf�gen.',0),
25012 => array('Graph::AddYN() Du hast versucht, einen leeren Plot zum Graph hinzuzuf�gen.',0),
25013 => array('Es k�nnen nur Standard-Plots zu multiplen Y-Achsen hinzugef�gt werden',0),
25014 => array('Graph::AddText() Du hast versucht, einen leeren Text zum Graph hinzuzuf�gen.',0),
25015 => array('Graph::AddLine() Du hast versucht, eine leere Linie zum Graph hinzuzuf�gen.',0),
25016 => array('Graph::AddBand() Du hast versucht, ein leeres Band zum Graph hinzuzuf�gen.',0),
25017 => array('Du benutzt GD 2.x und versuchst, ein Hintergrundbild in einem Truecolor-Bild zu verwenden. Um Hintergrundbilder mit GD 2.x zu verwenden, ist es notwendig, Truecolor zu aktivieren, indem die USE_TRUECOLOR-Konstante auf TRUE gesetzt wird. Wegen eines Bugs in GD 2.0.1 ist die Qualit�t der Schrift sehr schlecht, wenn Truetype-Schrift in Truecolor-Bildern verwendet werden.',0),
25018 => array('Falscher Dateiname f�r Graph::SetBackgroundImage() : "%s" muss eine g�ltige Dateinamenerweiterung (jpg,gif,png) haben, wenn die automatische Dateityperkennung verwenndet werden soll.',1),
25019 => array('Unbekannte Dateinamenerweiterung (%s) in Graph::SetBackgroundImage() f�r Dateiname: "%s"',2),

25020 => array('Graph::SetScale(): Dar Maximalwert muss gr��er sein als der Mindestwert.',0),
25021 => array('Unbekannte Achsendefinition f�r die Y-Achse. (%s)',1),
25022 => array('Unbekannte Achsendefinition f�r die X-Achse. (%s)',1),
25023 => array('Nicht unterst�tzter Y2-Achsentyp: "%s" muss einer von (lin,log,int) sein.',1),
25024 => array('Nicht unterst�tzter X-Achsentyp: "%s" muss einer von (lin,log,int) sein.',1),
25025 => array('Nicht unterst�tzte Tick-Dichte: %d',1),
25026 => array('Nicht unterst�tzter Typ der nicht angegebenen Y-Achse. Du hast entweder: 1. einen Y-Achsentyp f�r automatisches Skalieren definiert, aber keine Plots angegeben. 2. eine Achse direkt definiert, aber vergessen, die Tick-Dichte zu festzulegen.',0),
25027 => array('Kann cached CSIM "%s" zum Lesen nicht �ffnen.',1),
25028 => array('Apache/PHP hat keine Schreibrechte, in das CSIM-Cache-Verzeichnis (%s) zu schreiben. �berpr�fe die Rechte.',1),
25029 => array('Kann nicht in das CSIM "%s" schreiben. �berpr�fe die Schreibrechte und den freien Speicherplatz.',1),

25030 => array('Fehlender Skriptname f�r StrokeCSIM(). Der Name des aktuellen Skriptes muss als erster Parameter von StrokeCSIM() angegeben werden.',0),
25031 => array('Der Achsentyp muss mittels Graph::SetScale() angegeben werden.',0),
25032 => array('Es existieren keine Plots f�r die Y-Achse nbr:%d',1),
25033 => array('',0),
25034 => array('Undefinierte X-Achse kann nicht gezeichnet werden. Es wurden keine Plots definiert.',0),
25035 => array('Du hast Clipping aktiviert. Clipping wird nur f�r Diagramme mit 0 oder 90 Grad Rotation unterst�tzt. Bitte ver�ndere Deinen Rotationswinkel (=%d Grad) dementsprechend oder deaktiviere Clipping.',1),
25036 => array('Unbekannter Achsentyp AxisStyle() : %s',1),
25037 => array('Das Bildformat Deines Hintergrundbildes (%s) wird von Deiner System-Konfiguration nicht unterst�tzt. ',1),
25038 => array('Das Hintergrundbild scheint von einem anderen Typ (unterschiedliche Dateierweiterung) zu sein als der angegebene Typ. Angegebenen: %s; Datei: %s',2),
25039 => array('Hintergrundbild kann nicht gelesen werden: "%s"',1),

25040 => array('Es ist nicht m�glich, sowohl ein Hintergrundbild als auch eine Hintergrund-Landesflagge anzugeben.',0),
25041 => array('Um Landesflaggen als Hintergrund benutzen zu k�nnen, muss die Datei "jpgraph_flags.php" eingef�gt werden (per include).',0),
25042 => array('Unbekanntes Hintergrundbild-Layout',0),
25043 => array('Unbekannter Titelhintergrund-Stil.',0),
25044 => array('Automatisches Skalieren kann nicht verwendet werden, weil es unm�glich ist, einen g�ltigen min/max Wert f�r die Y-Achse zu ermitteln (nur Null-Werte).',0),
25045 => array('Die Schriftfamilien FF_HANDWRT und FF_BOOK sind wegen Copyright-Problemen nicht mehr verf�gbar. Diese Schriften k�nnen nicht mehr mit JpGraph verteilt werden. Bitte lade Dir Schriften von http://corefonts.sourceforge.net/ herunter.',0),
25046 => array('Angegebene TTF-Schriftfamilie (id=%d) ist unbekannt oder existiert nicht. Bitte merke Dir, dass TTF-Schriften wegen Copyright-Problemen nicht mit JpGraph mitgeliefert werden. Du findest MS-TTF-Internetschriften (arial, courier, etc.) zum Herunterladen unter http://corefonts.sourceforge.net/',1),
25047 => array('Stil %s ist nicht verf�gbar f�r Schriftfamilie %s',2),
25048 => array('Unbekannte Schriftstildefinition [%s].',1),
25049 => array('Schriftdatei "%s" ist nicht lesbar oder existiert nicht.',1),

25050 => array('Erstes Argument f�r Text::Text() muss ein String sein.',0),
25051 => array('Ung�ltige Richtung angegeben f�r Text.',0),
25052 => array('PANIK: Interner Fehler in SuperScript::Stroke(). Unbekannte vertikale Ausrichtung f�r Text.',0),
25053 => array('PANIK: Interner Fehler in SuperScript::Stroke(). Unbekannte horizontale Ausrichtung f�r Text.',0),
25054 => array('Interner Fehler: Unbekannte Grid-Achse %s',1),
25055 => array('Axis::SetTickDirection() sollte nicht mehr verwendet werden. Benutze stattdessen Axis::SetTickSide().',0),
25056 => array('SetTickLabelMargin() sollte nicht mehr verwendet werden. Benutze stattdessen Axis::SetLabelMargin().',0),
25057 => array('SetTextTicks() sollte nicht mehr verwendet werden. Benutze stattdessen SetTextTickInterval().',0),
25058 => array('TextLabelIntevall >= 1 muss angegeben werden.',0),
25059 => array('SetLabelPos() sollte nicht mehr verwendet werden. Benutze stattdessen Axis::SetLabelSide().',0),

25060 => array('Unbekannte Ausrichtung angegeben f�r X-Achsentitel (%s).',1),
25061 => array('Unbekannte Ausrichtung angegeben f�r Y-Achsentitel (%s).',1),
25062 => array('Label unter einem Winkel werden f�r die Y-Achse nicht unterst�tzt.',0),
25063 => array('Ticks::SetPrecision() sollte nicht mehr verwendet werden. Benutze stattdessen Ticks::SetLabelFormat() (oder Ticks::SetFormatCallback()).',0),
25064 => array('Kleinere oder gr��ere Schrittgr��e ist 0. �berpr�fe, ob Du f�lschlicherweise SetTextTicks(0) in Deinem Skript hast. Wenn dies nicht der Fall ist, bist Du eventuell �ber einen Bug in JpGraph gestolpert. Bitte sende einen Report und f�ge den Code an, der den Fehler verursacht hat.',0),
25065 => array('Tick-Positionen m�ssen als array() angegeben werden',0),
25066 => array('Wenn die Tick-Positionen und -Label von Hand eingegeben werden, muss die Anzahl der Ticks und der Label gleich sein.',0),
25067 => array('Deine von Hand eingegebene Achse und Ticks sind nicht korrekt. Die Skala scheint zu klein zu sein f�r den Tickabstand.',0),
25068 => array('Ein Plot hat eine ung�ltige Achse. Dies kann beispielsweise der Fall sein, wenn Du automatisches Text-Skalieren verwendest, um ein Liniendiagramm zu zeichnen mit nur einem Datenpunkt, oder wenn die Bildfl�che zu klein ist. Es kann auch der Fall sein, dass kein Datenpunkt einen numerischen Wert hat (vielleicht nur \'-\' oder \'x\').',0),
25069 => array('Grace muss gr��er sein als 0',0),

25070 => array('Deine Daten enthalten nicht-numerische Werte.',0),
25071 => array('Du hast mit SetAutoMin() einen Mindestwert angegeben, der gr��er ist als der Maximalwert f�r die Achse. Dies ist nicht m�glich.',0),
25072 => array('Du hast mit SetAutoMax() einen Maximalwert angegeben, der kleiner ist als der Minimalwert der Achse. Dies ist nicht m�glich.',0),
25073 => array('Interner Fehler. Der Integer-Skalierungs-Algorithmus-Vergleich ist au�erhalb der Grenzen  (r=%f).',1),
25074 => array('Interner Fehler. Der Skalierungsbereich ist negativ (%f) [f�r %s Achse]. Dieses Problem k�nnte verursacht werden durch den Versuch, \'ung�ltige\' Werte in die Daten-Vektoren einzugeben (z.B. nur String- oder NULL-Werte), was beim automatischen Skalieren einen Fehler erzeugt.',2),
25075 => array('Die automatischen Ticks k�nnen nicht gesetzt werden, weil min==max.',0),
25077 => array('Einstellfaktor f�r die Farbe muss gr��er sein als 0',0),
25078 => array('Unbekannte Farbe: %s',1),
25079 => array('Unbekannte Farbdefinition: %s, Gr��e=%d',2),

25080 => array('Der Alpha-Parameter f�r Farben muss zwischen 0.0 und 1.0 liegen.',0),
25081 => array('Das ausgew�hlte Grafikformat wird entweder nicht unterst�tzt oder ist unbekannt [%s]',1),
25082 => array('Es wurden ung�ltige Gr��en f�r Breite und H�he beim Erstellen des Bildes definiert (Breite=%d, H�he=%d).',2),
25083 => array('Es wurde eine ung�ltige Gr��e beim Kopieren des Bildes angegeben. Die Gr��e f�r das kopierte Bild wurde auf 1 Pixel oder weniger gesetzt.',0),
25084 => array('Fehler beim Erstellen eines tempor�ren GD-Canvas. M�glicherweise liegt ein Arbeitsspeicherproblem vor.',0),
25085 => array('Ein Bild kann nicht aus dem angegebenen String erzeugt werden. Er ist entweder in einem nicht unterst�tzen Format oder er repres�ntiert ein kaputtes Bild.',0),
25086 => array('Du scheinst nur GD 1.x installiert zu haben. Um Alphablending zu aktivieren, ist GD 2.x oder h�her notwendig. Bitte installiere GD 2.x oder versichere Dich, dass die Konstante USE_GD2 richtig gesetzt ist. Standardm��ig wird die installierte GD-Version automatisch erkannt. Ganz selten wird GD2 erkannt, obwohl nur GD1 installiert ist. Die Konstante USE_GD2 muss dann zu "false" gesetzt werden.',0),
25087 => array('Diese PHP-Version wurde ohne TTF-Unterst�tzung konfiguriert. PHP muss mit TTF-Unterst�tzung neu kompiliert und installiert werden.',0),
25088 => array('Die GD-Schriftunterst�tzung wurde falsch konfiguriert. Der Aufruf von imagefontwidth() ist fehlerhaft.',0),
25089 => array('Die GD-Schriftunterst�tzung wurde falsch konfiguriert. Der Aufruf von imagefontheight() ist fehlerhaft.',0),

25090 => array('Unbekannte Richtung angegeben im Aufruf von StrokeBoxedText() [%s].',1),
25091 => array('Die interne Schrift untest�tzt das Schreiben von Text in einem beliebigen Winkel nicht. Benutze stattdessen TTF-Schriften.',0),
25092 => array('Es liegt entweder ein Konfigurationsproblem mit TrueType oder ein Problem beim Lesen der Schriftdatei "%s" vor. Versichere Dich, dass die Datei existiert und Leserechte und -pfad vergeben sind. (wenn \'basedir\' restriction in PHP aktiviert ist, muss die Schriftdatei im Dokumentwurzelverzeichnis abgelegt werden). M�glicherweise ist die FreeType-Bibliothek falsch installiert. Versuche, mindestens zur FreeType-Version 2.1.13 zu aktualisieren und kompiliere GD mit einem korrekten Setup neu, damit die FreeType-Bibliothek gefunden werden kann.',1),
25093 => array('Die Schriftdatei "%s" kann nicht gelesen werden beim Aufruf von Image::GetBBoxTTF. Bitte versichere Dich, dass die Schrift gesetzt wurde, bevor diese Methode aufgerufen wird, und dass die Schrift im TTF-Verzeichnis installiert ist.',1),
25094 => array('Die Textrichtung muss in einem Winkel zwischen 0 und 90 engegeben werden.',0),
25095 => array('Unbekannte Schriftfamilien-Definition. ',0),
25096 => array('Der Farbpalette k�nnen keine weiteren Farben zugewiesen werden. Dem Bild wurde bereits die gr��tm�gliche Anzahl von Farben (%d) zugewiesen und die Palette ist voll. Verwende stattdessen ein TrueColor-Bild',0),
25097 => array('Eine Farbe wurde als leerer String im Aufruf von PushColor() angegegeben.',0),
25098 => array('Negativer Farbindex. Unpassender Aufruf von PopColor().',0),
25099 => array('Die Parameter f�r Helligkeit und Kontrast sind au�erhalb des zul�ssigen Bereichs [-1,1]',0),

25100 => array('Es liegt ein Problem mit der Farbpalette und dem GD-Setup vor. Bitte deaktiviere anti-aliasing oder verwende GD2 mit TrueColor. Wenn die GD2-Bibliothek installiert ist, versichere Dich, dass die Konstante USE_GD2 auf "true" gesetzt und TrueColor aktiviert ist.',0),
25101 => array('Ung�ltiges numerisches Argument f�r SetLineStyle(): (%d)',1),
25102 => array('Ung�ltiges String-Argument f�r SetLineStyle(): %s',1),
25103 => array('Ung�ltiges Argument f�r SetLineStyle %s',1),
25104 => array('Unbekannter Linientyp: %s',1),
25105 => array('Es wurden NULL-Daten f�r ein gef�lltes Polygon angegeben. Sorge daf�r, dass keine NULL-Daten angegeben werden.',0),
25106 => array('Image::FillToBorder : es k�nnen keine weiteren Farben zugewiesen werden.',0),
25107 => array('In Datei "%s" kann nicht geschrieben werden. �berpr�fe die aktuellen Schreibrechte.',1),
25108 => array('Das Bild kann nicht gestreamt werden. M�glicherweise liegt ein Fehler im PHP/GD-Setup vor. Kompiliere PHP neu und verwende die eingebaute GD-Bibliothek, die mit PHP angeboten wird.',0),
25109 => array('Deine PHP- (und GD-lib-) Installation scheint keine bekannten Grafikformate zu unterst�tzen. Sorge zun�chst daf�r, dass GD als PHP-Modul kompiliert ist. Wenn Du au�erdem JPEG-Bilder verwenden willst, musst Du die JPEG-Bibliothek installieren. Weitere Details sind in der PHP-Dokumentation zu finden.',0),

25110 => array('Dein PHP-Installation unterst�tzt das gew�hlte Grafikformat nicht: %s',1),
25111 => array('Das gecachete Bild %s kann nicht gel�scht werden. Problem mit den Rechten?',1),
25112 => array('Das Datum der gecacheten Datei (%s) liegt in der Zukunft.',1),
25113 => array('Das gecachete Bild %s kann nicht gel�scht werden. Problem mit den Rechten?',1),
25114 => array('PHP hat nicht die erforderlichen Rechte, um in die Cache-Datei %s zu schreiben. Bitte versichere Dich, dass der Benutzer, der PHP anwendet, die entsprechenden Schreibrechte f�r die Datei hat, wenn Du das Cache-System in JPGraph verwenden willst.',1),
25115 => array('Berechtigung f�r gecachetes Bild %s kann nicht gesetzt werden. Problem mit den Rechten?',1),
25116 => array('Datei kann nicht aus dem Cache %s ge�ffnet werden',1),
25117 => array('Gecachetes Bild %s kann nicht zum Lesen ge�ffnet werden.',1),
25118 => array('Verzeichnis %s kann nicht angelegt werden. Versichere Dich, dass PHP die Schreibrechte in diesem Verzeichnis hat.',1),
25119 => array('Rechte f�r Datei %s k�nnen nicht gesetzt werden. Problem mit den Rechten?',1),

25120 => array('Die Position f�r die Legende muss als Prozentwert im Bereich 0-1 angegeben werden.',0),
25121 => array('Eine leerer Datenvektor wurde f�r den Plot eingegeben. Es muss wenigstens ein Datenpunkt vorliegen.',0),
25122 => array('Stroke() muss als Subklasse der Klasse Plot definiert sein.',0),
25123 => array('Du kannst keine Text-X-Achse mit X-Koordinaten verwenden. Benutze stattdessen eine "int" oder "lin" Achse.',0),
25124 => array('Der Eingabedatenvektor mus aufeinanderfolgende Werte von 0 aufw�rts beinhalten. Der angegebene Y-Vektor beginnt mit leeren Werten (NULL).',0),
25125 => array('Ung�ltige Richtung f�r statische Linie.',0),
25126 => array('Es kann kein TrueColor-Bild erzeugt werden. �berpr�fe, ob die GD2-Bibliothek und PHP korrekt aufgesetzt wurden.',0),
25127 => array('The library has been configured for automatic encoding conversion of Japanese fonts. This requires that PHP has the mb_convert_encoding() function. Your PHP installation lacks this function (PHP needs the "--enable-mbstring" when compiled).',0),

/*
**---------------------------------------------------------------------------------------------
** Pro-version strings
**---------------------------------------------------------------------------------------------
*/

/*
**  jpgraph_table
*/

27001 => array('GTextTable: Ung�ltiges Argument f�r Set(). Das Array-Argument muss 2-- dimensional sein.',0),
27002 => array('GTextTable: Ung�ltiges Argument f�r Set()',0),
27003 => array('GTextTable: Falsche Anzahl von Argumenten f�r GTextTable::SetColor()',0),
27004 => array('GTextTable: Angegebener Zellenbereich, der verschmolzen werden soll, ist ung�ltig.',0),
27005 => array('GTextTable: Bereits verschmolzene Zellen im Bereich (%d,%d) bis (%d,%d) k�nnen nicht ein weiteres Mal verschmolzen werden.',4),
27006 => array('GTextTable: Spalten-Argument = %d liegt au�erhalb der festgelegten Tabellengr��e.',1),
27007 => array('GTextTable: Zeilen-Argument = %d liegt au�erhalb der festgelegten Tabellengr��e.',1),
27008 => array('GTextTable: Spalten- und Zeilengr��e m�ssen zu den Dimensionen der Tabelle passen.',0),
27009 => array('GTextTable: Die Anzahl der Tabellenspalten oder -zeilen ist 0. Versichere Dich, dass die Methoden Init() oder Set() aufgerufen werden.',0),
27010 => array('GTextTable: Es wurde keine Ausrichtung beim Aufruf von SetAlign() angegeben.',0),
27011 => array('GTextTable: Es wurde eine unbekannte Ausrichtung beim Aufruf von SetAlign() abgegeben. Horizontal=%s, Vertikal=%s',2),
27012 => array('GTextTable: Interner Fehler. Es wurde ein ung�ltiges Argument festgeleget %s',1),
27013 => array('GTextTable: Das Argument f�r FormatNumber() muss ein String sein.',0),
27014 => array('GTextTable: Die Tabelle wurde weder mit einem Aufruf von Set() noch von Init() initialisiert.',0),
27015 => array('GTextTable: Der Zellenbildbedingungstyp muss entweder TIMG_WIDTH oder TIMG_HEIGHT sein.',0),

/*
**  jpgraph_windrose
*/

22001 => array('Die Gesamtsumme der prozentualen Anteile aller Windrosenarme darf 100% nicht �berschreiten!\n(Aktuell max: %d)',1),
22002 => array('Das Bild ist zu klein f�r eine Skala. Bitte vergr��ere das Bild.',0),
22004 => array('Die Etikettendefinition f�r Windrosenrichtungen m�ssen 16 Werte haben (eine f�r jede Kompassrichtung).',0),
22005 => array('Der Linientyp f�r radiale Linien muss einer von ("solid","dotted","dashed","longdashed") sein.',0),
22006 => array('Es wurde ein ung�ltiger Windrosentyp angegeben.',0),
22007 => array('Es wurden zu wenig Werte f�r die Bereichslegende angegeben.',0),
22008 => array('Interner Fehler: Versuch, eine freie Windrose zu plotten, obwohl der Typ keine freie Windrose ist.',0),
22009 => array('Du hast die gleiche Richtung zweimal angegeben, einmal mit einem Winkel und einmal mit einer Kompassrichtung (%f Grad).',0),
22010 => array('Die Richtung muss entweder ein numerischer Wert sein oder eine der 16 Kompassrichtungen',0),
22011 => array('Der Windrosenindex muss ein numerischer oder Richtungswert sein. Du hast angegeben Index=%d',1),
22012 => array('Die radiale Achsendefinition f�r die Windrose enth�lt eine nicht aktivierte Richtung.',0),
22013 => array('Du hast dasselbe Look&Feel f�r die gleiche Kompassrichtung zweimal engegeben, einmal mit Text und einmal mit einem Index (Index=%d)',1),
22014 => array('Der Index f�r eine Kompassrichtung muss zwischen 0 und 15 sein.',0),
22015 => array('Du hast einen unbekannten Windrosenplottyp angegeben.',0),
22016 => array('Der Windrosenarmindex muss ein numerischer oder ein Richtungswert sein.',0),
22017 => array('Die Windrosendaten enthalten eine Richtung, die nicht aktiviert ist. Bitte berichtige, welche Label angezeigt werden sollen.',0),
22018 => array('Du hast f�r dieselbe Kompassrichtung zweimal Daten angegeben, einmal mit Text und einmal mit einem Index (Index=%d)',1),
22019 => array('Der Index f�r eine Richtung muss zwischen 0 und 15 sein. Winkel d�rfen nicht f�r einen regelm��igen Windplot angegeben werden, sondern entweder ein Index oder eine Kompassrichtung.',0),
22020 => array('Der Windrosenplot ist zu gro� f�r die angegebene Bildgr��e. Benutze entweder WindrosePlot::SetSize(), um den Plot kleiner zu machen oder vergr��ere das Bild im urspr�nglichen Aufruf von WindroseGraph().',0),
22021 => array('It is only possible to add Text, IconPlot or WindrosePlot to a Windrose Graph',0),

/*
**  jpgraph_odometer
*/

13001 => array('Unbekannter Nadeltypstil (%d).',1),
13002 => array('Ein Wert f�r das Odometer (%f) ist au�erhalb des angegebenen Bereichs [%f,%f]',3),

/*
**  jpgraph_barcode
*/

1001 => array('Unbekannte Kodier-Specifikation: %s',1),
1002 => array('datenvalidierung schlug fehl. [%s] kann nicht mittels der Kodierung "%s" kodiert werden',2),
1003 => array('Interner Kodierfehler. Kodieren von %s ist nicht m�glich in Code 128',1),
1004 => array('Interner barcode Fehler. Unbekannter UPC-E Kodiertyp: %s',1),
1005 => array('Interner Fehler. Das Textzeichen-Tupel (%s, %s) kann nicht im Code-128 Zeichensatz C kodiert werden.',2),
1006 => array('Interner Kodierfehler f�r CODE 128. Es wurde versucht, CTRL in CHARSET != A zu kodieren.',0),
1007 => array('Interner Kodierfehler f�r CODE 128. Es wurde versucht, DEL in CHARSET != B zu kodieren.',0),
1008 => array('Interner Kodierfehler f�r CODE 128. Es wurde versucht, kleine Buchstaben in CHARSET != B zu kodieren.',0),
1009 => array('Kodieren mittels CODE 93 wird noch nicht unterst�tzt.',0),
1010 => array('Kodieren mittels POSTNET wird noch nicht unterst�tzt.',0),
1011 => array('Nicht untrst�tztes Barcode-Backend f�r den Typ %s',1),

/*
** PDF417
*/

26001 => array('PDF417: Die Anzahl der Spalten muss zwischen 1 und 30 sein.',0),
26002 => array('PDF417: Der Fehler-Level muss zwischen 0 und 8 sein.',0),
26003 => array('PDF417: Ung�ltiges Format f�r Eingabedaten, um sie mit PDF417 zu kodieren.',0),
26004 => array('PDF417: die eigebenen Daten k�nnen nicht mit Fehler-Level %d und %d spalten kodiert werden, weil daraus zu viele Symbole oder mehr als 90 Zeilen resultieren.',2),
26005 => array('PDF417: Die Datei "%s" kann nicht zum Schreiben ge�ffnet werden.',1),
26006 => array('PDF417: Interner Fehler. Die Eingabedatendatei f�r PDF417-Cluster %d ist fehlerhaft.',1),
26007 => array('PDF417: Interner Fehler. GetPattern: Ung�ltiger Code-Wert %d (Zeile %d)',2),
26008 => array('PDF417: Interner Fehler. Modus wurde nicht in der Modusliste!! Modus %d',1),
26009 => array('PDF417: Kodierfehler: Ung�ltiges Zeichen. Zeichen kann nicht mit ASCII-Code %d kodiert werden.',1),
26010 => array('PDF417: Interner Fehler: Keine Eingabedaten beim Dekodieren.',0),
26011 => array('PDF417: Kodierfehler. Numerisches Kodieren bei nicht-numerischen Daten nicht m�glich.',0),
26012 => array('PDF417: Interner Fehler. Es wurden f�r den Binary-Kompressor keine Daten zum Dekodieren eingegeben.',0),
26013 => array('PDF417: Interner Fehler. Checksum Fehler. Koeffiziententabellen sind fehlerhaft.',0),
26014 => array('PDF417: Interner Fehler. Es wurden keine Daten zum Berechnen von Kodew�rtern eingegeben.',0),
26015 => array('PDF417: Interner Fehler. Ein Eintrag 0 in die Status�bertragungstabellen ist nicht NULL. Eintrag 1 = (%s)',1),
26016 => array('PDF417: Interner Fehler: Nichtregistrierter Status�bertragungsmodus beim Dekodieren.',0),


);

?>
