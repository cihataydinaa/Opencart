<?php
$index_list   = array();
$index_list[] = 'product.model';
$action = (!empty($_REQUEST['action'])) ? $_REQUEST['action'] : '';
if(file_exists('./config.php')) {
require_once './config.php';
}
else {
die("Aborting: config.php not found!");
}
if(!$db = cueh_db_connect()) {
die("Unable to connect to DB - Check Settings");
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Opencart MyISAM & INNODB</title>
<link href="//netdna.bootstrapcdn.com/bootstrap/3.0.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container">
<div class="well">
<ul>
<li>Dönüştür Düğmesine Bir Defa Tıklayıp Bekleyin.</li>
</ul>
</p>
</div>
<div class="panel panel-primary">
<div class="panel-heading">
<h3 class="panel-title">Seçenekler</h3>
</div>
<div class="panel-body">
<a href="cueh.php?action=engine" class="btn btn-success btn-lg" onclick="return confirm('Opencart veritabanı tablolarınızı MyISAM'den InnoDB'ye dönüştürmek istediğinizden emin misiniz??');">Veritabanını Değiştir</a><br><br>
<a href="cueh.php?action=indexes" class="btn btn-success btn-lg" onclick="return confirm('Opencart veritabanı tablolarınıza İndeks eklemek istediğinizden emin misiniz?');">Veritabanını İndexle</a>
</div>
</div>
<div class="panel panel-default">
<div class="panel-heading">
<h3 class="panel-title">Çıkış</h3>
</div>
<div class="panel-body">
<p><?php
switch($action) {
case 'engine':
cueh_switch_engine();
break;
case 'indexes':
cueh_table_indexes();
break;
case 'delete':
// Nothing yet
break;
default:
// Nothing yet
break;
}
?></p>
</div>
</div>
</div>
<script src="//netdna.bootstrapcdn.com/bootstrap/3.0.2/js/bootstrap.min.js"></script>
</body>
</html>
<?php
function cueh_table_indexes() {
global $db, $index_list;
$tables = cueh_get_tables(true);
if($tables && count($tables) > 0) {
cueh_log("Tablolara Dizin Ekleme");
// Loop through Tables
foreach($tables as $table_name => $table) {
// Loop through Columns
foreach($table['columns'] as $column_name => $column) {
$has_index   = false;
$needs_index = false;
// Does this column need an index?
if(substr($column_name, -3) == '_id') {
// Column ends in '_id'
$needs_index = true;
}
elseif(in_array($table_name.'.'.$column_name, $index_list)) {
// This column exists in the manual index list
$needs_index = true;
}
// Loop through the indexes for this column to determine if it has one already
if($column['indexes'] && !empty($column['indexes'])) {
foreach($column['indexes'] as $index) {
if($index['position'] == 1) {
// This column is in first position in an Index
$has_index = true;
}
}
}
if(!$has_index && $needs_index) {
// Has no Index and needs an Index
$sql = "ALTER TABLE `{$table_name}` ADD INDEX (  `{$column_name}` )";
if($output = $db->query($sql)) {
cueh_log("{$table_name}.{$column_name} - Dizin Eklendi",'success','BAŞARILI');
}
else {
cueh_log("{$table_name}.{$column_name} - Dizin Eklenemedi - ".$db->error,'danger','HATA');
}
}
elseif($needs_index) {
// Needs an Index but already has one
cueh_log("{$table_name}.{$column_name} - Dizin Zaten Var",'info','BİLGİ');
}
}
}
}
else {
cueh_log("İptal",'danger','HATA');
}
}
function cueh_switch_engine() {
global $db;
$tables = cueh_get_tables();
if($tables && count($tables) > 0) {
cueh_log("Veritabanı Değişimi Başladı");
foreach ($tables as $table_name => $table) {
if($table['engine'] != 'InnoDB') {
$sql = "ALTER TABLE `{$table_name}` ENGINE = INNODB";
if($rs = $db->query($sql)) {
cueh_log("{$table_name} {$table['engine']} 'dan InnoDB",'success','Başarılı');
}
else {
cueh_log("{$table_name} Motor Anahtarı Başarısız - ".$db->error,'danger','HATA');
}
}
else {
cueh_log("{$table_name} Zaten InnoDB",'info','Çevrilmiş');
}
}
}
else {
cueh_log("İptal",'danger','ERROR');
}
}
function cueh_get_tables($getindexes=false) {
global $db;
$tables = false;
$sql = "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA LIKE '".DB_DATABASE."'";
if($rs = $db->query($sql)) {
if($rs->num_rows > 0) {
// Table list loaded
cueh_log("{$rs->num_rows} Tablo");
$tables = array();
while ($row = $rs->fetch_assoc()) {
$table               = array();
$table['name']       = $row['TABLE_NAME'];
$table['engine']     = $row['ENGINE'];
$table['columns']    = false;
if($getindexes) {
// Get indexes first
$sqli = "SELECT *
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA LIKE '".DB_DATABASE."'
AND TABLE_NAME LIKE '".$table['name']."'";
$table['indexes'] = array();
if($rsi = $db->query($sqli)) {
while($indexes = $rsi->fetch_assoc()) {
$index             = array();
$index['name']     = $indexes['COLUMN_NAME'];
$index['key']      = $indexes['INDEX_NAME'];
$index['unique']   = ($indexes['NON_UNIQUE'] == 1) ? false : true; // Invert logic
$index['position'] = $indexes['SEQ_IN_INDEX'];
if(!isset($table['indexes'][$index['name']])) {
$table['indexes'][$index['name']] = array();
}
$table['indexes'][$index['name']][] = $index;
}
}
// Get Columns
$sqlc = "SELECT *
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA LIKE '".DB_DATABASE."'
AND TABLE_NAME LIKE '".$table['name']."'";
if($rsc = $db->query($sqlc)) {
$table['columns'] = array();
while($columns = $rsc->fetch_assoc()) {
$column            = array();
$column['name']    = $columns['COLUMN_NAME'];
$column['type']    = $columns['DATA_TYPE'];
$column['indexes'] = false;
if(isset($table['indexes'][$column['name']])) {
// If there are any Indexes for this column, add to Array
$column['indexes'] = $table['indexes'][$column['name']];
}
$table['columns'][$column['name']] = $column;
}
}
else {
cueh_log("Tabloda DB Sütunu Bulunamadı {$table['name']}",'danger','Hata');
}
}
$tables[$table['name']] = $table;
}
}
else {
// No tables found
cueh_log("Veritabanı Tablosu Bulunamadı",'danger','Hata');
}
}
else {
cueh_log("Hata: DB Tablo Listesi alınamıyor");
}
return $tables;
}
function cueh_log($input,$type='default',$label='') {
if($label) {
echo '<span class="label label-'.$type.'">'.$label.'</span> ';
}
echo $input."<br>";
}
/**
* Connect to Database using Config Settings
* @return MySQLi Connection Object
*/
function cueh_db_connect() {
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
return $db;
}
/* End of File */
