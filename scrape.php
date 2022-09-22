<?php
 
require 'vendor/autoload.php';
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function jobs_crawler( Crawler $crawler, array $dataArray ) {
    $crawler->filter( '.c-banner-card' )->each( function ( $node ) use ( &$dataArray ) {
    
        $employer = $node->filter( '.c-banner-card__subtitle' )->count() ? $node->filter( '.c-banner-card__subtitle' )->text() : '';
        $title = $node->filter( '.c-banner-card__title' )->text();
        $city = $node->filter( '.c-banner-card__column--extra' )->text();
        $job_page = $node->filter( 'a.c-banner-card' )->attr( 'href' );
    
        $subclient = new Client();
        $subcrawler = $subclient->request( 'GET', $job_page );
    
        $wp_class = $subcrawler->filter( 'body' )->attr( 'class' );
        $wp_id = substr( $wp_class, strcspn( $wp_class, '0123456789' ) );
    
        $tags = [];
        $tag = $subcrawler->filter( '.c-tag.c-tag--large' )->each( function ( $node ) {
            return $node->text();
        });
        array_push( $tags, $tag );
        $tags = implode( ", ", $tags[0] );
    
        $content = $subcrawler->filter( '.c-content' )->html();
        
        $info = $subcrawler->filter( '.c-widget.c-widget--information' );
    
        $info_arr = $info->filter( 'p' )->each( function ( $node ) {
            $category = str_starts_with( $node->text(), 'Industrija' ) ? substr( $node->text(), 10 ) : '';
            $job_subtype = str_starts_with( $node->text(), 'Vrsta zaposlenja' ) ? substr( $node->text(), 16 ) : '';
            return [$category, $job_subtype];
        });
        if ( is_array( $info_arr ) ) {
            $category = is_array( $info_arr[0] ) && array_key_exists( 0, $info_arr[0] ) ? $info_arr[0][0] : '';
            $job_subtype = is_array( $info_arr[1] ) && array_key_exists( 1, $info_arr[1] ) ? $info_arr[1][1] : '';
        }
    
        $application_cta = $info->filter( 'a' )->count() ? $info->filter( 'a' )->attr( 'href' ) : '';
        $applicattion_type = str_contains( $application_cta, "mailto:" ) ? "E-mail" : "Link";
        $application_link = $applicattion_type === 'Link' ? $application_cta : '';
        $application_email = $applicattion_type === 'E-mail' ? $application_cta : '';
    
        $job_type = '';
        $experience_level = '';
        $application_email_subject = '';
    
        echo $wp_id . "\n";
        echo $employer . " - " . $title . " - " . $city . "\n";
        echo $tags . "\n";
        echo $applicattion_type . " - " . $application_link . "\n";
        echo $category . "\t" . $job_subtype . "\n";
    
        $rowArray = [ 
            $title, $content, $category, $tags, $employer, $city, $job_type, $job_subtype, $experience_level, 
            $applicattion_type, $application_link, $application_email, $application_email_subject, $wp_id
        ];

        array_push( $dataArray, $rowArray );
    });

    return $dataArray;
}
 
$client = new Client();

$spreadsheet = new Spreadsheet();

$sheet = $spreadsheet->getActiveSheet();

$sheet->setCellValue( 'A1', 'Title' );
$sheet->setCellValue( 'B1', 'Content' );
$sheet->setCellValue( 'C1', 'Category' );
$sheet->setCellValue( 'D1', 'Tags' );
$sheet->setCellValue( 'E1', 'Employer' );
$sheet->setCellValue( 'F1', 'City' );
$sheet->setCellValue( 'G1', 'Job Type' );
$sheet->setCellValue( 'H1', 'Job Subtype' );
$sheet->setCellValue( 'I1', 'Experience Level' );
$sheet->setCellValue( 'J1', 'Application Type' );
$sheet->setCellValue( 'K1', 'Application Link' );
$sheet->setCellValue( 'L1', 'Application Email' );
$sheet->setCellValue( 'M1', 'Application Email Subject' );
$sheet->setCellValue( 'N1', 'WP ID' );

$dataArray = [];

$page = 1;
do {
    $url = "https://split-techcity.com/poslovi/page/" . $page ;
    $crawler = $client->request( 'GET', $url );
    if ( $client->getResponse()->getStatusCode() === 404 ) {
        break;
    }

    echo "\n\n---------- " . $page . " ----------\n\n\n";
    $dataArray = jobs_crawler( $crawler, $dataArray );

    $page++;
} while ( $client->getResponse() );

$spreadsheet->getActiveSheet()
    ->fromArray(
        $dataArray,
        NULL,
        'A2',
    );

$writer = new Xlsx( $spreadsheet );
$writer->save( 'jobs.xlsx' );
