<?php
/**
 * Sitemap
 *
 * Outputs a machine readable site map for search engines and robots.
 *
 * @category snippet
 * @version 1.1 (2017-08-18)
 * @license LGPL
 * @author Grzegorz Adamiak [grad], ncrossland, DivanDesign (http://www.DivanDesign.biz)
 * @internal @modx_category Navigation
 *
 * @param startid {integer} - Id of the 'root' document from which the sitemap starts. Default: 0.
 * @param format {string} - Which format of sitemap to use: sp (Sitemap Protocol used by Google), txt (text file with list of URLs), ror (Resource Of Resources). Default: sp.
 * @param seeThruUnpub {0; 1} - See through unpublished documents. Default: 1.
 * @param priority {string} - Name of TV which sets the relative priority of the document. If there is no such TV, this parameter will not be used. Default: 'sitemap_priority'.
 * @param changefreq {string} - Name of TV which sets the change frequency. If there is no such TV this parameter will not be used. Default: 'sitemap_changefreq'.
 * @param excludeTemplates {comma separated string} - Documents based on which templates should not be included in the sitemap. Comma separated list with names of templates. Default: ''.
 * @param excludeTV {string} - Name of TV (boolean type) which sets document exclusion form sitemap. If there is no such TV this parameter will not be used. Default: 'sitemap_exclude'.
 * @param xsl {string; integer} - URL to the XSL style sheet or doc ID of the XSL style sheet. Default: ''.
 * @param excludeWeblinks {0; 1} - Should weblinks be excluded? You may not want to include links to external sites in your sitemap, and Google gives warnings about multiple redirects to pages within your site. Default: 0.
 * @param ignoreSearchable {0; 1} - Ignore 'Searchable' checked for resources.Default: 0.
 * @param site_start {integer} - Change site_start resource id.Default: false.
 */

/* Parameters */
if(!isset($startid))          $startid = 0;
if(!isset($priority))         $priority = 'sitemap_priority';
if(!isset($changefreq))       $changefreq = 'sitemap_changefreq';
if(!isset($excludeTemplates)) $excludeTemplates = '';
if(!isset($excludeTV))        $excludeTV = 'sitemap_exclude';
if(!isset($xsl))              $xsl = '';
if(!isset($excludeWeblinks))  $excludeWeblinks = 1;
if(!isset($ignoreSearchable)) $ignoreSearchable = 0;
if(!isset($site_start))       $site_start = false;

$seeThruUnpub = (!isset($seeThruUnpub) || $seeThruUnpub) ? true : false;
$format       = (isset($format) && ($format !== 'ror')) ? $format : 'sp';
if (is_numeric($xsl)) {
    $xsl = evo()->makeUrl($xsl);
}

/* End parameters */

$docs = getDocs($startid, $priority, $changefreq, $excludeTV, $seeThruUnpub);

$rs = db()->select('id, templatename', '[+prefix+]site_templates');
while ($row = db()->getRow($rs)){
    $allTemplates[$row['id']] = $row['templatename'];
}

if (!empty ($excludeTemplates)){
    $excludeTemplates = explode(',', $excludeTemplates);
    foreach ($excludeTemplates as $template){
        $template = trim($template);
        if (is_numeric($template) && isset($allTemplates[$template])){
            unset($allTemplates[$template]);
        }elseif (trim($template) && in_array($template, $allTemplates)){
            unset($allTemplates[array_search($template, $allTemplates)]);
        }
    }
}

$_ = array();
// filter out documents which shouldn't be included
foreach ($docs as $doc){
    if(!isset($allTemplates[$doc['template']]))          continue;
    if($doc[$excludeTV])                                 continue;
    if($doc['changefreq'] === 'exclude')                 continue;
    if(!$doc['published'])                               continue;
    if(!$ignoreSearchable && !$doc['searchable'])        continue;
    if($excludeWeblinks && $doc['type'] === 'reference') continue;
    if($doc['id'] == evo()->documentIdentifier)          continue;
    $_[$doc['id']] = $doc;
}
$docs = $_;
unset ($_, $excludeTemplates);

$site_editedon = get_site_editedon();
if($site_editedon) {
    if( $site_start !== false && is_numeric($site_start) ){
        $docs[$site_start]['editedon'] = $site_editedon;
    }else{
        $docs[evo()->config['site_start']]['editedon'] = $site_editedon;
    }
}

// build sitemap in specified format
// ---------------------------------------------

$output = array();
switch ($format){
    // Next case added in version 1.0.4
    case 'ulli': // UL List
        $output[] = '<ul class="sitemap">';
        // TODO: Sort the array on Menu Index
        // TODO: Make a nested ul-li based on the levels in the document tree.
        foreach ($docs as $doc){
            $s  = '  <li class="sitemap">';
            $s .= '<a href="'.$doc['url'].'" class="sitemap">' . $doc['pagetitle'] . '</a>';
            $s .= '</li>';
            $output[] = $s;
        }

        $output[] = '</ul>';
    break;

    case 'txt': // plain text list of URLs

        foreach ($docs as $doc){
            $output[] = $doc['url'];
        }

    break;

    case 'ror': // TODO
    default: // Sitemap Protocol
        $output[] = '<?xml version="1.0" encoding="'.evo()->config["modx_charset"].'"?>';
        if ($xsl) $output[] ='<?xml-stylesheet type="text/xsl" href="'.$xsl.'"?>';

        $output[] ='<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($docs as $doc) {
            $output[] = '    <url>';
            $output[] = '        <loc>'.htmlentities($doc['url']).'</loc>';
            if($doc['editedon'])
                $output[] = '        <lastmod>'.date('Y-m-d', $doc['editedon']).'</lastmod>';
            $output[] = '        <priority>'.$doc['priority'].'</priority>';
            $output[] = '        <changefreq>'.$doc['changefreq'].'</changefreq>';
            $output[] = '    </url>';
        }

        $output[] = '</urlset>';

}

return implode("\n",$output);
