<?php
namespace App; use Illuminate\Database\Eloquent\Model; class File extends Model { protected $guarded = array(); public $timestamps = false; function deleteFile() { try { Storage::disk($this->driver)->delete($this->path); } catch (\Exception $spd54c56) { \Log::error('File.deleteFile Error: ' . $spd54c56->getMessage(), array('exception' => $spd54c56)); } } public static function getProductFolder() { return 'images/product'; } }