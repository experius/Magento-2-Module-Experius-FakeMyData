<?php


namespace Experius\FakeMyData\Faker\Provider\Got;

class Person extends \Faker\Provider\en_US\Person {

    protected static $firstNameMale = array(
        'John','Gregor','Tyrion','Joffrey','Sandor','Ramsay','Eddard','Hodor','Petyr','Bran','Robb','Daario','Theon',
        'Tommon','High','Stannis','Jamie','Tormund','Oberyn','Renling','Podrick','Davos','Khal'
    );

    protected static $firstNameFemale = array(
        'Kalisi','Arya','Sansa','Cersei','Shae','Margaery','Melisandre','Ygritte','Brienne','Catelyn','Missandei',
        'Meera','Myrcella'
    );

    protected static $lastName = array(
        'Snow','Targaryen','Stark','Clegane','Lannister','Baratheon','Tyrell','Clegane','Bolton','of Tarth','Stark',
        'Naharis','Greyjoy','Baratheon','Septon','Giantsbane','Martell','Baratheon','Payne','Seaworth','Drogo','Baelish',
        'Sparrow'
    );

}