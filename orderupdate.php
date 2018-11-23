<?php 

// Call the database connection settings
require( "../../wp-config.php" );

$input_file = "Technicote_orders.txt";
	echo "dir name is " . dirname(__FILE__);

// Connect to database
$con = connectToDB(DB_HOST, DB_USER, DB_PASSWORD);

// Delete old data prior to load of new data
deleteStagingData($con);

// Load input file into tc_order_stage and tc_order_detail_stage tables
loadNewDataFromCSV($con, $input_file);

// Delete old data from prod tables
//deleteProdData($con);

// Move data from stage table to prod tables
//stagingToProd($con);

echo "File Processed Sucessfully<br>";
echo "Records Processed      = " . $recCount . "<br>";
echo "Orders Inserted        = " . $orderCount . "<br>";
echo "Order Details Inserted = " . $lineCount . "<br>";
exit(0);


// Connect to database
function connectToDB($hostname, $username, $password) 
{
    try {
        $con = new PDO("mysql:host=$hostname;dbname=wp_technicote958", $username, $password);
        $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $con->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    } 

    catch (PDOException $e) {
        echo "ERROR: Connection failed: " . $e->getMessage();
        exit(16);
    }
    
    return $con;
}

// Deletes old data from tc_order_stage and tc_order_detail_stage tables
function deleteStagingData($con)
{
    //Start our transaction.
    $con->beginTransaction();

    try {
        $sql = $con->prepare("DELETE FROM tc_order_detail_stage");
        $sql->execute();
        $con->commit();
    }
    catch (PDOException $e) {
        mailStatus("ERROR: Delete from tc_order_detail_stage failed: " . $e->getMessage()); 
        $con->rollBack();
        return false;
    }

    //Start our transaction.
    $con->beginTransaction();

    try {
        $sql = $con->prepare("DELETE FROM tc_order_stage");
        $sql->execute();
        $con->commit();
    }
    catch (PDOException $e) {
        mailStatus("ERROR: Delete from tc_order_stage failed: " . $e->getMessage()); 
        $con->rollBack();
        return false;
    }
    
    return true;
}

// Deletes old prod data from tc_order and tc_order_detail tables
function deleteProdData($con)
{
    //Start our transaction.
    $con->beginTransaction();

    try {
        $sql = $con->prepare("DELETE FROM tc_order_detail");
        $sql->execute();
        $con->commit();
    }
    catch (PDOException $e) {
        mailStatus("ERROR: Delete from tc_order_detail failed: " . $e->getMessage()); 
        $con->rollBack();
        return false;
    }

    //Start our transaction.
    $con->beginTransaction();

    try {
        $sql = $con->prepare("DELETE FROM tc_order");
        $sql->execute();
        $con->commit();
    }
    catch (PDOException $e) {
        mailStatus("ERROR: Delete from tc_order failed: " . $e->getMessage()); 
        $con->rollBack();
        return false;
    }
    
    return true;
}

// Move data from staging to prod tables
function stagingToProd($con)
{
    try {
        $sql = $con->prepare("INSERT INTO tc_order SELECT * FROM tc_order_stage");
        $sql->execute();
    }
    catch (PDOException $e) {
        mailStatus("ERROR: Failed moving data from tc_order_stage to tc_order: " . $e->getMessage()); 
        return false;
    }
    
    try {
        $sql = $con->prepare("INSERT INTO tc_order_detail SELECT * FROM tc_order_detail_stage");
        $sql->execute();
    }
    catch (PDOException $e) {
        mailStatus("ERROR: Failed moving data from tc_order_detail_stage to tc_order_detail: " . $e->getMessage()); 
        return false;
    }
    
    return true;
}

// Populate tc_order_stage and tc_order_detail_stage from input CSV file
function loadNewDataFromCSV($con, $input_file)
{
    global $recCount;
    global $orderCount;
    global $lineCount;
    
    $recCount = 0;
    $orderCount = 0;
    $lineCount = 0;
    
    $all_data = csvToArray($input_file);
    $prev_order_num = '          ';
    $i=0;
    foreach ($all_data as $data) {
        
        if ($prev_order_num != $data['co_num']) {
            $prev_order_num = $data['co_num'];

            try {
                $sql = $con->prepare("INSERT INTO tc_order_stage (order_number, customer_number, customer_name, customer_city, customer_state, customer_country, purchase_order) 
                VALUES (:order_number, :customer_number, :name, :city, :state, :country, :po)");
                if (i==0)
                    print_r($data);
                $i = $i + 1;
                echo "customer " . $data['cust_num'] . "-" . $data[1] . "<br>";
                $sql->bindParam(':order_number', $data['co_num']);
                $sql->bindParam(':customer_number', $data['cust_num']);
                $sql->bindParam(':name', $data['name']);
                $sql->bindParam(':city', $data['city']);
                $sql->bindParam(':state', $data['state']);
                $sql->bindParam(':country', $data['country']);
                $sql->bindParam(':po', $data['cust_po']);
                $sql->execute();
                $last_inser_order_id = $con->lastInsertId();
            }
            catch (PDOException $e) {
                mailStatus("ERROR: Insert to tc_order_stage failed for order# " . $data['co_num'] . $e->getMessage());
                return false;
            }

        }
        
        try {
            $sql = $con->prepare("INSERT INTO tc_order_detail_stage (order_id, line_number, item_number, quantity, width, unit_of_measure, order_date, request_date, promise_date, ship_date, status) 
            VALUES (:order_id, :line_number, :item_number, :quantity, :width, :unit_of_measure, :order_date, :request_date, :promise_date, :ship_date, :status)");
            
            $sql->bindParam(':order_id', $last_inser_order_id);
            $sql->bindParam(':line_number', $data['co_line']);
            $sql->bindParam(':item_number', $data['item']);
            $sql->bindParam(':quantity', $data['qty']);
            $sql->bindParam(':width', $data['width']);
            $sql->bindParam(':unit_of_measure', $data['u_m']);
            $sql->bindParam(':order_date', date("Y/m/d", strtotime($data['order_date'])));
            $sql->bindParam(':request_date', date("Y/m/d", strtotime($data['request_date'])));
            $sql->bindParam(':promise_date', date("Y/m/d", strtotime($data['promise_date'])));
            $sql->bindParam(':ship_date', date("Y/m/d", strtotime($data['ship_date'])));
            $sql->bindParam(':status', $data['stat']);
            $sql->execute();
        }
        catch (PDOException $e) {
            mailStatus("ERROR: Insert to tc_order_detail_stage failed for order# " . $data['co_num'] . "line number " . $data['co_line'] . $e->getMessage());
            return false;
        }

    }

    return true;
}

// Create  CSV to Array function
function csvToArray($filename = '', $delimiter = '|')
{
    if (!file_exists($filename) || !is_readable($filename)) {
        return false;
    }

    $header = NULL;
    $result = array();
    if (($handle = fopen($filename, 'r')) !== FALSE) {
        while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            if (!$header) {
                $header = $row;
            }
            else {
                $result[] = array_combine($header, $row);
            }
        }
        fclose($handle);
    }

    return $result;
}

// Mail Errors from Orders Update to Admin
function mailStatus($msg) 
{
    wp_mail( 'sstromick@gmail.com', 'Orders DB Update Failed', $msg );
}

// End functions for orders db update process

?>