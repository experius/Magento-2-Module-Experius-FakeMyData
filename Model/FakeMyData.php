<?php

/*

TODO split up in models wich are load in the construct and defined in the di.xml
TODO make configuration settings
TODO Abort when env is not in dev mode

*/

namespace Experius\FakeMyData\Model;

class FakeMyData {

    private $faker;

    private $resourceConnection;

    private $connection;

    private $fakeEmailPrefix = '';

    private $fakeEmailDomain = 'example.com';

    private $excludedEmailDomains =  'experiuss.nl,heesbeen.nu';

    private $fakedData= [];

    private $fakedDataTypes = ['customer','customer_address','order','order_grid'];

    public function __construct(
        \Faker\Factory $faker,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    )
    {
        $this->faker = $faker;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
    }

    public function getSelect($tableName,$excludeEmailField='email',$join=false){
        $select = $this->connection->select();
        $select->from($tableName,'*');

        if($join) {
            $select->joinLeft(['customers' => 'customer_entity'], 'customers.entity_id' . ' = ' . $tableName . '.parent_id', ['email']);
        }

        foreach(explode(',',$this->excludedEmailDomains) as $excludedEmailDomain){
            $select->where($excludeEmailField . " NOT LIKE '%" . $excludedEmailDomain ."'");
        }

        return $select;
    }

    public function fakeAll(){
        foreach($this->fakedDataTypes as $type) {
            $this->fakeData($type);
        }
    }

    public function getFakeCustomerData($results,$customerIdKey='entity_id'){
        foreach($results as $result) {

            $customerId = $result[$customerIdKey];

            /* TODO change local base on storeID */
            $faker = $this->faker->create('nl_NL');
            $faker->seed($customerId);

            $firstname = $faker->firstName;
            $lastname = $faker->lastName;
            $dateOfBirthYear = $faker->dateTimeThisCentury->format('Y');

            $this->fakedData[$customerId] = [
                'firstname'=>$firstname,
                'lastname'=>$lastname,
                'name' => $firstname . ' ' . $lastname,
                'email'=> $this->getFakeEmail([$firstname,$lastname,$dateOfBirthYear]),
                'street' => $this->getFakeStreet([$faker->streetName, $faker->buildingNumber]),
                'postcode' => $faker->postcode,
                'telephone' => $faker->phoneNumber,
                'city'=> $faker->city,
                'company' => $faker->company
            ];

        }
    }

    public function fakeData($type){

        switch($type){
            case 'customer':

                $select = $this->getSelect('customer_entity','email');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'entity_id');
                $this->updateData('entity_id',['firstname'=>'firstname','lastname'=>'lastname','email'=>'email'],'customer_entity',$results);

                break;
            case 'customer_address':

                $select = $this->getSelect('customer_address_entity','email',true);
                $results = $this->connection->fetchAll($select);

                /* TODO fake each address different */

                $this->getFakeCustomerData($results,'parent_id');
                $this->updateData('parent_id',['firstname'=>'firstname','lastname'=>'lastname','postcode'=>'postcode','telephone'=>'telephone','city'=>'city','street'=>'street'],'customer_address_entity',$results);

                break;

            case 'order':

                $select = $this->getSelect('sales_order','customer_email');
                $select->where('customer_id IS NOT NULL');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'customer_id');
                $this->updateData('customer_id',['customer_firstname'=>'firstname','customer_lastname'=>'lastname','customer_email'=>'email'],'sales_order',$results);

                $select = $this->getSelect('sales_order','customer_email');
                $select->where('customer_id IS NULL');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'entity_id');
                $this->updateData('entity_id',['customer_firstname'=>'firstname','customer_lastname'=>'lastname','customer_email'=>'email'],'sales_order',$results);

                break;
            case 'order_grid':

                $select = $this->getSelect('sales_order_grid','customer_email');
                $select->where('customer_id IS NOT NULL');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'customer_id');
                $this->updateData('customer_id',['shipping_name'=>'name','billing_name'=>'name','customer_name'=>'name','customer_email'=>'email'],'sales_order_grid',$results);

                $select = $this->getSelect('sales_order_grid','customer_email');
                $select->where('customer_id IS NULL');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'entity_id');
                $this->updateData('entity_id',['shipping_name'=>'name','billing_name'=>'name','customer_name'=>'name','customer_email'=>'email'],'sales_order_grid',$results);

                break;
            case 'invoice':
                break;
            case 'invoice_grid':
                break;
            case 'shipment':
                break;
            case 'shipment_grid':
                break;
            case 'quote':
                break;
            case 'quote_address':
                break;
        }

    }

    public function updateData($identifier,$fields,$tableName,$results){
        $tableName = $this->resourceConnection->getTableName($tableName);
        $this->updateFlat($identifier,$results,$fields,$tableName);
    }

    public function getFakeEmail($values){
        return $this->fakeEmailPrefix . str_replace([" ","'",],'.',implode('.',$values)) . '@' . $this->fakeEmailDomain;
    }

    /* TODO Get number of addresslines from settings */
    public function getFakeStreet($values){
        $addressLines = 3;

        if($addressLines==1){
            return implode(" ",$values);
        }

        if($addressLines==2){
            return $values[0] . "\n" . $values[1] . (isset($values[3])) ? " " . $values[3] : '';
        }

        return implode("\n",$values);
    }

    /* TODO Optional update only if field has value */
    public function updateFlat($identifier,$results,$fields,$tableName){
        foreach($results as $result){

            $updateData = [];
            $fakeData = $this->fakedData[$result[$identifier]];

            foreach($fields as $field=>$fakeDataArrayKey){
                $updateData[$field] = $fakeData[$fakeDataArrayKey];
            }

            $update = $this->connection->update($tableName,$updateData,[$identifier." = ? " => $result[$identifier]]);

        }
    }
}
