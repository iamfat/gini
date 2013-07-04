<?php

namespace ORM {

    class User extends \ORM\Object {

        var $name        = 'string:50';

    }

    class Department extends \ORM\Object {

        var $name        = 'string:50';

    }

    class Account extends \ORM\Object {

        var $lab = 'object:lab';
        var $department = 'object:department';

    }

    class Lab extends \ORM\Object {

        var $name        = 'string:50';
        var $gender      = 'bool';
        var $money       = 'double,default:0';
        var $description = 'string:*,null';

    }


}

namespace Test\Unit\System {

    class Those extends \Model\Test\Unit {
        
        function setup() {
            class_exists('\\Model\\Those');
        }

        function test_those() {
            // (user#1, billing_account[lab=lab#1]<department) billing_department
            // $account = new \ORM\Account;
            $user = a('user');
            $user->db()->adjust_table($user->name(), $user->schema());

            $lab = a('lab');
            $lab->db()->adjust_table($lab->name(), $lab->schema());
            
            $account = a('account');
            $account->db()->adjust_table($account->name(), $account->schema());
            
            $departments = those('department')
                ->which_is('department')->of(
                    a('user')->whose('id')->is(1)
                )
                ->and_which_is('department')->of(
                    an('account')->whose('lab')->is(
                        a('lab')->whose('id')->is(1)
                    )
                );


            $departments->node->finish();
            var_dump($departments->node->SQL);
            // a('user')->whose('id')->is(1)->id;

        }

        function teardown() {

        }

    }


}


