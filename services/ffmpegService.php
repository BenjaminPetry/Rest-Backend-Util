<?php

class FFMPEGService
{
  // REQUIRES the following config
  // VIDEO_TMP

  public static function test()
  {
    return self::getMeta(1);
    //return self::getWaveForm("P1-1.mp4");
  }

  public static function getVideo($videoId)
  {
      $getVideo = DB::prepare("SELECT * FROM video WHERE ID = :videoId LIMIT 1;");
      $tmp = DB::fetch($getVideo, array("videoId"=> $videoId));
      return $tmp ? $tmp : null;
  }

  public static function getMeta($videoId)
  {
    $meta = self::getMetaData($videoId);
    $video = self::getVideo($videoId);
    $videoFilename = $video["filename"];
    if (!$meta)
    {
      return null;
    }
    $json = self::getVideoMetaFile(basename($videoFilename)."-wave.json",false);
    $waveFormData = json_decode(file_get_contents($json));
    $meta["waveform"] = $waveFormData;
    return $meta;
  }

  private static function getMetaData($videoId)
  {
    $query = DB::prepare("SELECT * FROM `video-meta` WHERE video_id = :videoId");
    $tmp = DB::fetch($query, array("videoId"=>$videoId));
    return $tmp ? $tmp : null;
  }

  private static function updateMetaData($videoId,$field, $value)
  {
    $allowedFields = array("duration","waveform","thumbnail");
    if (!in_array($field,$allowedFields))
    {
      throw new RuntimeException("Field '$field' cannot be set.");
    }
    $meta = self::getMetaData($videoId); 
    if (!$meta)
    {
      $query = DB::prepare("INSERT INTO `video-meta` (video_id) VALUES (:videoId)");
      if (!DB::execute($query, array("videoId"=>$videoId)))
      {
        throw new RuntimeException("Could not add meta data entry for video $videoId");
      }
    }
    $query = DB::prepare("UPDATE `video-meta` SET $field = :fieldValue WHERE video_id = :videoId");
    if (!DB::execute($query, array("fieldValue" => $value, "videoId"=>$videoId)))
    {
      throw new RuntimeException("Could not update meta data entry '$field' for video $videoId");
    }
  }

  public static function createMetaData($videoId)
  {
    $meta = self::getMetaData($videoId);
    $meta = $meta ? $meta : array();
    $video = self::getVideo($videoId);
    $videoFilename = $video["filename"];
    $videoFile = self::getVideoFile($videoFilename);
    if (!array_key_exists("duration",$meta) || !$meta["duration"])
    {
      $output = self::ffmpeg("ffmpeg -i \"$videoFile\"","Calculate duration",true);
      $regex = "/Duration: ([0-9][0-9]:[0-9][0-9]:[0-9][0-9].[0-9][0-9])/s";
      foreach ($output as $line)
      {
        if (preg_match($regex, $line, $matches))
        {
          $duration = $matches[1];
           self::updateMetaData($videoId, "duration", $duration);
          $meta["duration"] = $duration;
          break;
        }
      }
    }
    if (!array_key_exists("thumbnail",$meta) || !$meta["thumbnail"] || !file_exists(self::getVideoMetaFile($meta["thumbnail"],false)))
    {
      $thumbnailFile = self::getVideoMetaFile(basename($videoFilename).".png");
      $output = self::ffmpeg("-i \"$videoFile\" -vframes 1 -an -s 320x180 -ss 30 \"$thumbnailFile\"","Create thumbnail",true);
      if (!file_exists($thumbnailFile))
      {
        throw new RuntimeException("Thumbnail Generation Failed");
      }
      self::updateMetaData($videoId, "thumbnail", basename($thumbnailFile));
    }
    if ((!array_key_exists("waveform",$meta) || true ||  !$meta["waveform"])&& array_key_exists("duration",$meta) )
    {
      $seconds = floor(parseTime($meta["duration"])*10);
      $waveFormFile = self::getVideoMetaFile(basename($videoFilename)."-wave.png",false);
      if (!file_exists($waveFormFile))
      {
        $cmd = "-i \"$videoFile\" -filter_complex \"aformat=channel_layouts=mono,compand,showwavespic=s=".$seconds."x100\" -frames:v 1 \"$waveFormFile\"";
        $output = self::ffmpeg($cmd,"Create Waveform",true);
        if (!file_exists($waveFormFile))
        {
          throw new RuntimeException("Could not create waveform for video $videoId");
        }
      }
      $waveFormJSON = self::getVideoMetaFile(basename($videoFilename)."-wave.json",false);
      $jsonWaveForm = self::convertWaveFormToJSON($waveFormFile);
      $jsonMinima = self::findMinima($jsonWaveForm);
      if (count($jsonWaveForm)==0)
      {
        throw new RuntimeException("Could not read JSON Data from waveform.");
      }
      if (!file_put_contents($waveFormJSON, json_encode(array("min"=>$jsonMinima, "data"=>$jsonWaveForm))))
      {
        throw new RuntimeException("Could not store waveform JSON data for video $videoId");
      }
      self::updateMetaData($videoId, "waveform", basename($waveFormJSON));
    }
  }

  private static function getTimeAsString($value)
  {
    if (is_string($value))
    {
      if ("".floatval($value) == $value)
      {
        return formatSeconds(floatval($value));
      }
      return $value;
    }
    return formatSeconds($value);
  }

  private static function findMinima($data)
  {
    $window = 5;
    $threshold = 0.05;
    $result = array();
    $history = array();
    $currentMin = 1;
    $lastElement = 0;
    for ($x = 0; $x < count($data); $x++)
    {
      $v = $data[$x];
      $history[] = $v;
      if ($v > $threshold)
      {
        $currentMin = min($currentMin,$v);
      }
      if (count($history) > $window)
      {
        $element = array_shift($history);
        if ($element == $currentMin)
        {
          $lastMin = $currentMin;
          $currentMin = 1;
          foreach ($history as $value)
          {
            if ($value > $threshold)
            {
              $currentMin = min($currentMin,$value);
            }
          }
          if ($lastMin < $currentMin && ($element <= $lastElement || $lastElement < $threshold))
          {
            $result[] = $x;
          }
        }
        $lastElement = $element;
      }
    }
    return $result;
  }

  private static function convertWaveFormToJSON($file)
  {
    $result = array();
    $im     = imagecreatefrompng($file);
    $size   = getimagesize($file);
    $width  = $size[0];
    $height = $size[1];

    for($x=0;$x<$width;$x++)
    {
        $result[$x] = 0;
        for($y=0;$y<$height/2;$y++)
        {
            $rgb = imagecolorat($im, $x, $y);
            if (($rgb & 0xFF000000) == 0)
            {
              $result[$x] = round(($height/2-$y)/$height/2,3);
              break;
            }
        }
    }
    return $result;
  }

  public static function getState($jobId)
  {
    $query = DB::prepare("SELECT * FROM `ffmpeg-jobs` WHERE ID = :id");
    $job = DB::fetch($query,array("id"=>$jobId));
    if (!$job)
    {
      return 1; // jobs seems to be completed
    }
    if (!file_exists($job["state_file"]))
    {
      throw new RuntimeException("Job status file does not exist!");
    }



  }

  private static function ffmpeg($parameterString,$descr="",$syncronous = false)
  {
    global $config;
    $query = DB::prepare("INSERT INTO `ffmpeg-jobs` (state_file,cmd,task_descr) VALUES (:state_file,:cmd,:task_descr);");
    $state_filename = self::getTmpFile("ffmpeg.log");
    $cmd = $config["ffmpeg-exec"]." ".$parameterString.($syncronous ? " 2>&1" : " </dev/null >/dev/null 2>\"$state_filename\" &");
    if (!$syncronous)
    {
      DB::execute($query, array("state_file"=>$state_filename,"cmd"=>$cmd,"task_descr"=>$descr));
    }
    $output = array();
    exec($cmd,$output);
    return $output;
  }

  private static function getTmpFile($file,$newIfExists=true)
  {
    global $config;
    if ($newIfExists)
    {
      $file = getUniqueFileName($config["tmp-folder"], $file);
    }
    return $config["tmp-folder"].$file;
  }

  private static function getVideoMetaFile($file,$newIfExists=true)
  {
    global $config;
    if ($newIfExists)
    {
      $file = getUniqueFileName($config["video-meta-folder"], $file);
    }
    return $config["video-meta-folder"].$file;
  }

  private static function getVideoFile($videoname)
  {
    global $config;
    return $config["video-folder"].$videoname;
  }

// public static function extract($videoname, $start, $end)
//   {
//     global $config, $cwd;
//     $startS = formatSeconds($start);
//     $endS = formatSeconds($end);
//     $startF = str_replace(".","_",str_replace(":","_",$startS));
//     $endF = str_replace(".","_",str_replace(":","_",$endS));
//     $startFFMPEG = formatSeconds($start-1);
//     $startFFMPEG2 = formatSeconds(1);
//     $startFFMPEG3 = formatSeconds($start);
//     $diff = $end - $start;
//     $diffS = formatSeconds($diff);
//     $video = self::getByName($videoname);
//     $inputFile = $config["video-folder"].$videoname;
//     $outputDir = $cwd."/".$config["export-folder"];
//     $outputFilename = getUniqueFileName($outputDir,$video["participant"]."-".$video["take"]."_$startF-$endF",".mp4");
//     $outputFile = $outputDir.$outputFilename;
//     $startS = formatSeconds(floatval($start));
//     $endS = formatSeconds(floatval($end));
//     $command = $config["ffmpeg-exec"]." -ss $startFFMPEG -i \"$inputFile\" -ss $startFFMPEG2 -t $diffS -c copy  \"$outputFile\"";
//     $command = $config["ffmpeg-exec"]." -ss $startFFMPEG3 -i \"$inputFile\" -t $diffS \"$outputFile\"";
//     shell_exec($command);
//     //print_r(shell_exec($config["ffmpeg-exec"]));
//     return array("link"=>(file_exists($outputFile) ? $config["export-folder"].$outputFilename : null), "cmd"=>$command);
//   }
}