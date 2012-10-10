<?php
/**
 * Defines the database classes
 *
 * @author Damien Walsh <walshd0@cs.man.ac.uk>
 */
 
/**
 * Schema integrity exception
 *
 * @package lightgroup
 */
final class SchemaException extends Exception
{
}
 
/**
 * Database Class
 * Provides interaction with the stored data.
 *
 * @package lightgroup
 */
class DB 
{
  /**
   * The location of the data files.
   * 
   * @var string
   */
  public static $dataPath = './db/';
  
  /**
   * Data types
   *
   * @var array
   */
  private static $types = array(
    
    // Integer types
    'int' => 8,
    
    // String types
    'str32' => 32,
    'str64' => 64,
    
    // Blob (binary/text) types
    'blob' => 32
  );
  
  /**
   * Convert an array of data into a binstring
   * 
   * @param array $data The data.
   * @param integer $pad Optionally a padding value to pad to with NUL bytes.
   * @return string
   */
  private static function getChrs($data, $pad = -1)
  {
    $result = '';
    $bCount = 0;
    
    // Convert $data?
    if(!is_array($data))
    {
      $data = str_split(strval($data));
    }
    
    // Write in data
    foreach($data as $d)
    {
      $result .= $d;
      $bCount ++;
    }
    
    // Pad with NUL (0x00) bytes
    if($pad)
    {
      for(; $bCount < $pad; $bCount ++)
      {
        $result .= chr(0x00);
      }
    }
    
    return $result;
  }
  
  private static function unpackSegment($segment)
  {
    $realData = array();
    $characters = str_split(strval($segment));
    
    foreach($characters as $c)
    {
      if($c != chr(0x00))
      {
        $realData[] = $c; 
      }
    }
    
    return implode($realData);
  }

  /** 
   * Truncate a table.
   * ! Removes all data from the table instantly ! 
   * 
   * @param string $tableName The table to truncate. 
   * @return boolean.
   */
  public static function truncate($tableName)
  {
    // Easy, just clear the file, no schema data is stored 
    // in datafiles.
    $tableDataFile = self::$dataPath . '/' . $tableName . '.table/data';
    
    if(!is_writeable($tableDataFile))
    {
      // Can't write to the data file
      throw new FileNotFoundException($tableDataFile);
    }
   
    // Open for writing
    $truncateHandle = fopen($tableDataFile, 'w');
    fclose($truncateHandle);

    // Remove blob files associated with the table
    $tableBlobsPath = self::$dataPath . '/' . $tableName . '.table/blobs/';
    
    if(!is_writable($tableBlobsPath))
    {
      throw new PermissionDeniedException($tableBlobsPath); 
    }
    
    // Remove all files
    if($tableBlobsHandle = opendir($tableBlobsPath))
    {
      while(($file = readdir($tableBlobsHandle)) !== false)
      {
        if($file != '.' && $file != '..')
        {
          unlink($tableBlobsPath . $file);
        }
      }
    } 

    return true;
  } 
 
  /** 
   * Print rows.
   * 
   * @param string $tableName The table to select from.
   * @return Result
   */
  public static function select($tableName, $predicate = null) 
  {
    // Retrieve table structure
    $structure = DB::getTableCols($tableName);
    
    // Calculate widths
    $widths = array();
    foreach($structure as $columnName => $column)
    {
      $widths[$columnName] = self::$types[$column['type']];
      
      // If the column descriptor is bigger than the actual data size,
      // force the column descriptor length as the size.
      if(strlen($columnName) > $widths[$columnName])
      {
        $widths[$columnName] = strlen($columnName);
      }
    }
    
    // Draw headers
    print '+';
    print str_repeat('-', array_sum($widths) - 1);
    print '+' . PHP_EOL;
    print '|';
    
    // Print the names of the columns
    foreach($structure as $columnName => $column)
    {
      print str_pad(' ' . $columnName . ($column['primary'] ? '*' : ''), self::$types[$column['type']] - 1) . '|';
    }
    
    // Next line
    print PHP_EOL . '+';
    print str_repeat('-', array_sum($widths) - 1);
    print '+' . PHP_EOL;
     
    // Get rows
    $rows = DB::getRowRange($tableName, 0, -1, $predicate);
    
    // Print each row
    foreach($rows as $row)
    {
      print '|';
      
      foreach($row as $columnName => $columnValue)
      {
        // Do not print out the full blob name
        if($structure[$columnName]['type'] == 'blob')
        {
          print str_pad(' -> ' . $columnValue, 31) . '|';
        }
        else
        {
          print str_pad(' ' . $columnValue, 
            self::$types[$structure[$columnName]['type']] - 1) . '|';
        }
      }
      
      // Ready for next row/footer line...
      print PHP_EOL;
    }
    
    // Footer line
    print '+';
    print str_repeat('-', array_sum($widths) - 1);
    print '+' . PHP_EOL;
    
    return true;
  }
  
  /**
   * Write a row into a table.
   *
   * @param string $tableName The table to write to.
   * @param array $values An associative array containing the values.
   * @return boolean
   */
  public static function insert($tableName, $values)
  {
    // Open the data file
    $tableDataFile = self::$dataPath . '/' . $tableName . '.table/data';
    
    if(!is_writeable($tableDataFile))
    {
      // Can't write to the data file
      throw new FileNotFoundException($tableDataFile);
    }
    
    // Read lines
    $tableDataHandle = fopen($tableDataFile, 'a');
    
    if(!$tableDataHandle)
    {
      throw new IOException($tableDataFile);
    }
  
    // Get the columns for the specified table.
    $columns = DB::getTableCols($tableName);
    
    // Get the primary key value
    $pKeyValue = current($values);
    
    // Write to the end of the file
    foreach($columns as $columnName => $column)
    {
      // Write the segment
      $segSize = self::$types[$column['type']];
     
      // Blob?
      if($column['type'] == 'blob')
      { 
        $segData = DB::getChrs(
          DB::linkBlob($tableName, $values[$columnName]),
          self::$types[$column['type']]);
      }
      else
      {
        $segData = DB::getChrs(substr($values[$columnName], 0,
          self::$types[$column['type']]),
          self::$types[$column['type']]);
      }

      fwrite($tableDataHandle, $segData);
    }
    
    // Close up
    fclose($tableDataHandle);
  }

  /** 
   * Link a blob to a table.
   * 
   * @param string $tableName The table to link to.
   * @param string $blobData The data to store.
   * @return string
   */
  private static function linkBlob($tableName, $blobData)
  {
    // Path to the blobs directory for the table
    $blobsPath = self::$dataPath . '/' . $tableName . '.table/blobs/';

    // Check it is possible to create the blob.
    if(!File::isWritable($blobsPath))
    {
      throw new FileNotFoundException($blobsPath);
    }

    // Find a unique filename
    $fName = '';
    while($fName == '' || file_exists($blobsPath . $fName))
    {
       $fName = substr(md5(microtime()), 0, 16);
    } 

    // Write data into the blobfile
    file_put_contents($blobsPath . $fName, $blobData);

    return $fName;
  }
 
  /** 
   * Unlink a blob from a table.
   * 
   * @param string $tableName The table to link to.
   * @param string $blobID The ID of the blob to remove.
   * @return boolean 
   */
  private static function unlinkBlob($tableName, $blobID)
  {
    // Path to the blob file
    $blobPath = self::$dataPath . '/' . $tableName .
      '.table/blobs/' . $blobID;

    // Check it is possible to create the blob.
    if(!File::isWritable($blobPath))
    {
      throw new FileNotFoundException($blobPath);
    }

    // Remove the blob
    unlink($blobPath);

    return true;
  } 

  private static function typeExists($type)
  {
    return array_key_exists($type, self::$types);
  }


  /**
   * Deletes specified tuples from a table.
   * Only deletes rows satisfying $predicate, if present.
   * Returns the number of deleted rows. 
   * 
   * @param string $tableName The table to act on.
   * @param Predicate $predicate Optionally a predicate.
   * @return integer
   */
  public static function delete($tableName, $predicate = null)
  {  
    // Get structure
    $structure = DB::getTableCols($tableName);
  
    // Calculate widths
    $widths = array();
    foreach($structure as $columnName => $column)
    {
      $widths[$columnName] = self::$types[$column['type']];
    }

    // Open the data file
    $tableDataFile = self::$dataPath . '/' . $tableName . '.table/data';
    
    if(!is_readable($tableDataFile))
    {
      // Can't read data file
      throw new FileNotFoundException($tableDataFile);
    }
    
    $tableDataHandle = fopen($tableDataFile, 'r');
    
    // Collect new data
    $rows = array(); 

    // Count affected rows
    $affectedRows = 0;
 
    // Start reading
    while(!feof($tableDataHandle))
    {
      // Read a row
      $row = array();
      
      // Build up the row 
      foreach($structure as $columnName => $column)
      {
        $segment = fread($tableDataHandle, self::$types[$column['type']]);
        
        // Nothing read?
        if(strlen($segment) == 0)
        {
          break 2;
        }
        
        $row[$columnName] = DB::unpackSegment($segment);
      }
      
      // Check the row against the predicate if present
      if(!$predicate || !$predicate->val($row))
      {
        $rows[] = $row;
      }
      else
      {
        $alteredRows ++;
      }
      
    }
    
    // Truncate
    DB::truncate($tableName);

    // Rewrite
    foreach($rows as $row)
    {
      DB::insert($tableName, $row);
    } 

    return $alteredRows;
  }
 

  /**
   * Update specified fields of a table using an associative array mask.
   * Only updates rows satisfying $predicate, if present.
   * Returns the number of affected rows. 
   * 
   * @param string $tableName The table to act on.
   * @param array $changes An associative array mask of changes to make.
   * @param Predicate $predicate Optionally a predicate.
   * @return integer
   */
  public static function update($tableName, $changes, $predicate = null)
  {  
    // Get structure
    $structure = DB::getTableCols($tableName);
  
    // Calculate widths
    $widths = array();
    foreach($structure as $columnName => $column)
    {
      $widths[$columnName] = self::$types[$column['type']];
    }

    // Open the data file
    $tableDataFile = self::$dataPath . '/' . $tableName . '.table/data';
    
    if(!is_readable($tableDataFile))
    {
      // Can't read data file
      throw new FileNotFoundException($tableDataFile);
    }
    
    $tableDataHandle = fopen($tableDataFile, 'r');
    
    // Collect new data
    $rows = array(); 

    // Count affected rows
    $affectedRows = 0;
 
    // Start reading
    while(!feof($tableDataHandle))
    {
      // Read a row
      $row = array();
      
      // Build up the row 
      foreach($structure as $columnName => $column)
      {
        $segment = fread($tableDataHandle, self::$types[$column['type']]);
        
        // Nothing read?
        if(strlen($segment) == 0)
        {
          break 2;
        }
        
        $row[$columnName] = DB::unpackSegment($segment);
      }
      
      // Check the row against the predicate if present
      if(!$predicate || $predicate->val($row))
      {
        // Perform the update - merge one array into the other
        foreach($structure as $cName => $cInfo)
        {
          // If the type is a blob, unlink it, it is now stale
	  if($cInfo['type'] == 'blob')
	  {
	    DB::unlinkBlob($tableName, $row[$cName]);
	  }
         
          if(array_key_exists($cName, $changes))
          {
            $row[$cName] = $changes[$cName];
          }
        }
        
        $alteredRows ++;
      }
      
      $rows[] = $row;
    }
    
    // Truncate
    DB::truncate($tableName);

    // Rewrite
    foreach($rows as $row)
    {
      DB::insert($tableName, $row);
    } 

    return $alteredRows;
  }
 

  /**
   * Read a range of rows directly from a table with no filtering.
   * 
   */
  private static function getRowRange($tableName, $start = 0, $count = -1, $predicate = null)
  {  
    // Get structure
    $structure = DB::getTableCols($tableName);
  
    // Calculate widths
    $widths = array();
    foreach($structure as $columnName => $column)
    {
      $widths[$columnName] = self::$types[$column['type']];
    }

    // Read along to the start position
    // The start position is absolute - a row MUST begin at the position.
    $startPosition = $start * array_sum($widths);
    
    // Open the data file
    $tableDataFile = self::$dataPath . '/' . $tableName . '.table/data';
    
    if(!is_readable($tableDataFile))
    {
      // Can't read data file
      throw new FileNotFoundException($tableDataFile);
    }
    
    $tableDataHandle = fopen($tableDataFile, 'r');
    
    // Seek to start position
    fseek($tableDataHandle, $startPosition);
    
    // Collect rows
    $rows = array();
    $rowsLeft = $count;
    
    // Start reading
    while(($count == -1 || $rowsLeft > 0) && !feof($tableDataHandle))
    {
      // Read a row
      $row = array();
      
      foreach($structure as $columnName => $column)
      {
        $segment = fread($tableDataHandle, self::$types[$column['type']]);
        
        // Nothing read?
        if(strlen($segment) == 0)
        {
          break 2;
        }
        
        $row[$columnName] = DB::unpackSegment($segment);
      }
      
      // Check the row against the predicate if present
      if(!$predicate || $predicate->val($row))
      {
        $rows[] = $row;
      }
      
      $rowsLeft --;
    }
    
    return $rows;
  }
  
  private static function getTableCols($tableName)
  {
    // Collect table column info
    $columns = array();
  
    // Read column data file for the table
    $tableDefsFile = self::$dataPath . '/' . $tableName . '.table/definition';
    
    if(!is_readable($tableDefsFile))
    {
      // Can't read table defs file
      throw new FileNotFoundException($tableDefsFile);
    }
    
    // Read lines
    $tableDefsHandle = fopen($tableDefsFile, 'r');
    
    if(!$tableDefsHandle)
    {
      throw new IOException($tableDefsFile);
    }
    
    $tableDefs = fread($tableDefsHandle, filesize($tableDefsFile));
    fclose($tableDefsHandle);
    
    // Read each line
    $columnDefs = preg_split('/\n/', $tableDefs, -1, PREG_SPLIT_NO_EMPTY);
    
    foreach($columnDefs as $columnDef)
    {
      $columnDetails = preg_split('/[\t\s]/', $columnDef, -1, PREG_SPLIT_NO_EMPTY);
      
      // Check type
      if(!DB::typeExists($columnDetails[0]))
      {
        throw new SchemaException('Column type "' . $columnDetails[0] . 
          '" is undefined in table ' . $tableName);
      }
      
      // Check name
      if(array_key_exists($columnDetails[1], $columns))
      {
        throw new SchemaException('Duplicate column "' . $columnDetails[1] . 
          '" in table ' . $tableName);
      }
      
      // Add to collecton
      $columns[$columnDetails[1]] = array(
        'type' => $columnDetails[0],
        'primary' => (count($columnDetails) > 2 && $columnDetails[2] == 'primary')
      );
    }
    
    return $columns;
  }
}
