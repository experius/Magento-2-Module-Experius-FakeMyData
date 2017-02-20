<?php

/*

TODO split up in models wich are loaded in the construct and defined in the di.xml
TODO add a delete option to keep the database small and use only last 100 records over everything

*/

namespace Experius\FakeMyData\Model;

class FakeMyData {

    private $faker;

    private $state;

    private $resourceConnection;

    private $connection;

    private $encryptor;

    private $scopeConfig;

    private $passwordHash;

    private $fakeEmailPrefix;

    private $fakeEmailDomain;

    private $password;

    private $customProvider = false;

    private $excludedEmailDomains;

    private $fakedData= [];

    private $stores;

    private $storeRepository;

    private $fakedDataTypes = [
        'sales_invoice_grid',
        'sales_shipment_grid',
        'sales_creditmemo_grid',
        'customer',
        'customer_address',
        'order',
        'order_grid',
        'newsletter',
        'quote'
    ];

    private $addressLines = 1;

    public function __construct(
        \Faker\Factory $faker,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\State $state,
        \Magento\Store\Model\StoreRepository $storeRepository
    )
    {
        $this->faker = $faker;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->encryptor = $encryptor;
        $this->scopeConfig = $scopeConfig;
        $this->state = $state;
        $this->storeRepository = $storeRepository;
    }

    public function initStores(){
        $stores = $this->storeRepository->getList();
        foreach($stores as $store){
            $this->stores[$store->getId()] = $this->scopeConfig->getValue('general/locale/code',\Magento\Store\Model\ScopeInterface::SCOPE_STORE,$store->getId());
        }
    }

    public function getLocaleByStoreId($storeId){
        if(isset($this->stores[$storeId])){
            return $this->stores[$storeId];
        }
        return 'en_US';
    }

    public function getExcludedEmailDomains(){
        if(!$this->excludedEmailDomains){
            $this->excludedEmailDomains = $this->scopeConfig->getValue('fakemydata/general/excluded_email_domains');
        }
        return $this->excludedEmailDomains;
    }

    public function getFakeEmailDomain(){
        if(!$this->fakeEmailDomain){
            $this->fakeEmailDomain = $this->scopeConfig->getValue('fakemydata/general/fake_email_domain');
        }
        return $this->fakeEmailDomain;
    }

    public function getFakeEmailPrefix(){
        if(!$this->fakeEmailPrefix){
            $this->fakeEmailPrefix = $this->scopeConfig->getValue('fakemydata/general/fake_email_prefix');
        }
        return $this->fakeEmailPrefix;
    }

    public function getPassword(){
        if(!$this->password){
            $this->password = $this->scopeConfig->getValue('fakemydata/general/password');
        }
        return $this->password;
    }

    public function getCustomProvider(){
        if(!$this->customProvider){
            $this->customProvider = $this->scopeConfig->getValue('fakemydata/general/customprovider');
        }
        return $this->customProvider;
    }

    public function getSelect($tableName,$excludeEmailField='email',$join){
        $select = $this->connection->select();
        $select->from($tableName,'*');

        if($join) {
            $select->joinLeft(['customers' => 'customer_entity'], 'customers.entity_id' . ' = ' . $tableName . '.parent_id', ['email']);
        }

        foreach(explode(',',$this->getExcludedEmailDomains()) as $excludedEmailDomain){
            $select->where($excludeEmailField . " NOT LIKE '%" . $excludedEmailDomain ."'");
        }

        return $select;
    }

    public function fakeAll(){

        if(\Magento\Framework\App\State::MODE_DEVELOPER != $this->state->getMode()){
            echo "Wow you're not in develop mode. Don't run this on production environments";
            exit;
        }

        $this->initStores();

        foreach($this->fakedDataTypes as $type) {
            $this->fakeData($type);
        }
    }

    public function stringToUniqueInt($value){
        return base_convert(md5($value), 10, 10);
    }

    public function getSeed($value){
        if(!is_numeric($value)){
                return $this->stringToUniqueInt($value);
        }
        return $value;
    }

    /* TODO make unique address work */

    public function getFakeCustomerData($results,$identifier='entity_id',$uniqueAddress=false){
        foreach($results as $result) {

            $seed = $this->getSeed($result[$identifier]);

            $locale = 'en_US';
            if(isset($result['store_id'])){
                $locale = $this->getLocaleByStoreId($result['store_id']);
            }

            $faker = $this->faker->create($locale);
            $faker->seed($seed);

            if($this->getCustomProvider()) {
                $customProvider = $this->getCustomProvider();
                $class = "\\Experius\\FakeMyData\\Faker\\Provider\\" . $customProvider . "\\Person";
                $faker->addProvider(new $class($faker));
            }

            $firstname = $faker->firstName;
            $lastname = $faker->lastName;
            $dateOfBirthYear = $faker->dateTimeThisCentury->format('Y');

            $fakeData = [
                'firstname'=>$firstname,
                'lastname'=>$lastname,
                'name' => $firstname . ' ' . $lastname,
                'email'=> $this->getFakeEmail([$firstname,$lastname,$dateOfBirthYear]),
                'street' => $this->getFakeStreet([$faker->streetName, $faker->buildingNumber]),
                'postcode' => $faker->postcode,
                'telephone' => $faker->phoneNumber,
                'city'=> $faker->city,
                'company' => $faker->company,
                'password_hash' => $this->getPasswordHash()
            ];

            $fakeData['address'] = $fakeData['street'] . "\n" . $fakeData['postcode'] . "\n". $fakeData['city'];

            $this->fakedData[$seed] = $fakeData;

        }
    }

    public function fakeData($type){

        switch($type){
            case 'customer':

                $select = $this->getSelect('customer_entity','email',false);
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'entity_id');
                $this->updateData('entity_id',['firstname'=>'firstname','lastname'=>'lastname','email'=>'email','password_hash'=>'password_hash'],'customer_entity',$results,'entity_id');

                break;
            case 'customer_address':

                $select = $this->getSelect('customer_address_entity','email','customer_id');
                $results = $this->connection->fetchAll($select);

                /* TODO fake each address different */

                $this->getFakeCustomerData($results,'parent_id');
                $this->updateData('parent_id',['firstname'=>'firstname','lastname'=>'lastname','postcode'=>'postcode','telephone'=>'telephone','city'=>'city','street'=>'street'],'customer_address_entity',$results,'entity_id');

                break;

            case 'order':

                $select = $this->getSelect('sales_order','customer_email',false);
                $select->where('customer_id IS NOT NULL');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'customer_id');
                $this->updateData('customer_id',['customer_firstname'=>'firstname','customer_lastname'=>'lastname','customer_email'=>'email'],'sales_order',$results,'entity_id');

                $select = $this->getSelect('sales_order','customer_email',false);
                $select->where('customer_id IS NULL');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'entity_id');
                $this->updateData('entity_id',['customer_firstname'=>'firstname','customer_lastname'=>'lastname','customer_email'=>'email'],'sales_order',$results,'entity_id');

                break;
            case 'order_grid':

                $select = $this->getSelect('sales_order_grid','customer_email',false);
                $select->where('customer_id IS NOT NULL');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'customer_id');
                $this->updateData('customer_id',['shipping_name'=>'name','billing_name'=>'name','customer_name'=>'name','customer_email'=>'email'],'sales_order_grid',$results,'entity_id');

                $select = $this->getSelect('sales_order_grid','customer_email',false);
                $select->where('customer_id IS NULL');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'entity_id');
                $this->updateData('entity_id',['shipping_name'=>'name','billing_name'=>'name','customer_name'=>'name','customer_email'=>'email'],'sales_order_grid',$results,'entity_id');

                break;

            case 'sales_invoice_grid':
            case 'sales_shipment_grid':
            case 'sales_creditmemo_grid':
                $select = $this->getSelect($type,$type.'.customer_email',false);
                $select->joinLeft(['sales_order_grid' => 'sales_order_grid'], 'sales_order_grid.entity_id' . ' = '.$type.'.order_id', ['customer_id'=>'customer_id']);
                $select->where('sales_order_grid.customer_id IS NOT NULL');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'customer_id');
                $this->updateData('customer_id',['billing_name'=>'name','customer_name'=>'name','customer_email'=>'email','billing_address'=>'address','shipping_address'=>'address'],$type,$results,'entity_id');

                $select = $this->getSelect($type,$type.'.customer_email',false);
                $select->joinLeft(['sales_order_grid' => 'sales_order_grid'], 'sales_order_grid.entity_id' . ' = '.$type.'.order_id', ['customer_id'=>'customer_id']);
                $select->where('sales_order_grid.customer_id IS NULL');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'order_id');
                $this->updateData('order_id',['billing_name'=>'name','customer_name'=>'name','customer_email'=>'email','billing_address'=>'address','shipping_address'=>'address'],$type,$results,'entity_id');
                break;
            case 'quote':

                $select = $this->getSelect('quote','customer_email',false);
                $select->where('customer_id IS NOT NULL');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'customer_id');
                $this->updateData('customer_id',['customer_firstname'=>'firstname','customer_lastname'=>'lastname','customer_email'=>'email'],'quote',$results,'entity_id');

                /* Todo Join with order Table */
                $select = $this->getSelect('quote','customer_email',false);
                $select->where('customer_id IS NULL');
                $select->where('customer_firstname IS NOT NULL OR customer_lastname IS NOT NULL OR customer_email IS NOT NULL');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'entity_id');
                $this->updateData('entity_id',['customer_firstname'=>'firstname','customer_lastname'=>'lastname','customer_email'=>'email'],'quote',$results,'entity_id');

                break;
            case 'quote_address':
                break;
            case 'review':
                break;
            case 'newsletter':

                $select = $this->getSelect('newsletter_subscriber','subscriber_email',false);
                $select->where('customer_id IS NOT NULL');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'customer_id');
                $this->updateData('customer_id',['subscriber_email'=>'email'],'newsletter_subscriber',$results,'subscriber_id');

                $select = $this->getSelect('newsletter_subscriber','subscriber_email',false);
                $select->where('subscriber_id IS NOT NULL');
                $results = $this->connection->fetchAll($select);
                $this->getFakeCustomerData($results,'customer_id');
                $this->updateData('subscriber_id',['subscriber_email'=>'email'],'newsletter_subscriber',$results,'subscriber_id');

                break;
        }

    }

    public function updateData($identifier,$fields,$tableName,$results,$updateIdentifier){
        $tableName = $this->resourceConnection->getTableName($tableName);
        $this->updateFlat($identifier,$results,$fields,$tableName,$updateIdentifier);
    }

    public function getFakeEmail($values){
        return $this->getFakeEmailPrefix() . str_replace([" ","'",],'.',implode('.',$values)) . '@' . $this->getFakeEmailDomain();
    }

    public function getPasswordHash(){
        if(!$this->passwordHash) {
            $this->passwordHash = $this->encryptor->getHash($this->getPassword(), true);
        }
        return $this->passwordHash;
    }

    public function getAddressLines(){
        if(!$this->addressLines){
            $this->addressLines = $this->scopeConfig->getValue('customer/address/street_lines');
        }
        return $this->addressLines;
    }

    public function getFakeStreet($values){
        $addressLines = $this->addressLines;

        if($addressLines==1){
            return implode(" ",$values);
        }

        if($addressLines==2){
            return $values[0] . "\n" . $values[1] . (isset($values[3])) ? " " . $values[3] : '';
        }

        return implode("\n",$values);
    }

    /* TODO Optional update only if field has value */
    public function updateFlat($identifier,$results,$fields,$tableName,$updateIdentifier){

        foreach($results as $result){

            $seed = $this->getSeed($result[$identifier]);

            $updateData = [];

            $fakeData = $this->fakedData[$seed];

            foreach($fields as $field=>$fakeDataArrayKey){
                $updateData[$field] = $fakeData[$fakeDataArrayKey];
            }

            $this->connection->update($tableName,$updateData,[$updateIdentifier." = ? " => $result[$updateIdentifier]]);

        }
    }
}
