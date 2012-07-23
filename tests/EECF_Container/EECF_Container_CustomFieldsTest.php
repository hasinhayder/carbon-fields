<?php 

class EECF_Container_CustomFieldsTest extends WP_UnitTestCase {
    public $plugin_slug = 'ecf';

    function setUp() {
        parent::setUp();
    }

    public function testValidSaveRequest() {
        $container = new EECF_Container_CustomFields('Test Container');
        $this->assertFalse( $container->is_valid_save() );

        // Invalid Nonce
        $_REQUEST[$container->get_nonce_name()] = $_POST[$container->get_nonce_name()] = 'foo';
        $this->assertFalse( $container->is_valid_save() );

        // Valid Nonce
        $_REQUEST[$container->get_nonce_name()] = $_POST[$container->get_nonce_name()] = wp_create_nonce($container->get_nonce_name());
        $this->assertTrue( $container->is_valid_save() );
        $this->assertTrue( $container->is_valid_save() );

        $container->detach();

        // Autosave
        // TODO: this case cannot be tested becuase it requires the definition of a constant - DOING_AUTOSAVE
    }

    public function testRegisterEqualFieldNamesForDifferentPostTypes() {
        // prepare container
        $container1 = new EECF_Container_CustomFields('Test Container 1');
        $container1->setup(array(
            'post_type' => 'bar'
        ));
        $container1->add_fields(array(
            EECF_Field::factory('text', 'test_field'),
        ));

        $container2 = new EECF_Container_CustomFields('Test Container 2');
        $container2->setup(array(
            'post_type' => 'foo'
        ));
        $container2->add_fields(array(
            EECF_Field::factory('text', 'test_field'),
        ));
    }

    public function testRegisterEqualFieldNamesForSamePostTypes() {
        // prepare container
        $container1 = new EECF_Container_CustomFields('Test Container 1');
        $container1->setup(array(
            'post_type' => 'bar'
        ));
        $container1->add_fields(array(
            EECF_Field::factory('text', 'test_field'),
        ));

        $container2 = new EECF_Container_CustomFields('Test Container 2');
        $container2->setup(array(
            'post_type' => 'bar'
        ));

        try {
            $container2->add_fields(array(
                EECF_Field::factory('text', 'test_field'),
            ));
        } catch (EECF_Exception $e) {
            return;
        }

        // exception must be thrown
        $this->assertTrue(false);
    }

    /**
     * @group slow
     */
    public function testSaveSimpleFieldCheckDatabase() {
        global $wpdb;
        // prepare container
        $container = new EECF_Container_CustomFields('Test Container');
        $container->setup(array(
            'post_type' => 'foo'
        ));
        $container->add_fields(array(
            EECF_Field::factory('text', 'test_field'),
        ));

        // Prepare POST
        $_POST['_test_field'] = 'Lorem Ipsum';

        // execute
        $container->save(123);

        // check
        $db_value = $wpdb->get_results('
            SELECT meta_value FROM ' . $wpdb->postmeta . '
            WHERE post_id="123" AND meta_key="_test_field"
        ', ARRAY_A);

        $this->assertCount(1, $db_value);
        $this->assertArrayHasKey('meta_value', $db_value[0]);
        $this->assertEquals($db_value[0]['meta_value'], 'Lorem Ipsum');
    }

    /**
     * @group slow
     */
    public function testSaveSimpleFieldCheckLoad() {
        global $wpdb;
        // prepare container
        $container = new EECF_Container_CustomFields('Test Container');
        $container->setup(array(
            'post_type' => 'foo'
        ));
        $container->add_fields(array(
            EECF_Field::factory('text', 'test_field'),
        ));

        // Prepare POST
        $_POST['_test_field'] = 'Lorem Ipsum';

        // execute
        $container->save(123);
        $container->load();

        // check
        $fields = $container->get_fields();

        $this->assertEquals($fields[0]->get_name(), '_test_field');
        $this->assertEquals($fields[0]->get_value(), 'Lorem Ipsum');

        // cleanup
        $container->detach();
    }

    /**
     * @group slow
     */
    public function testSaveRepeaterAndCheckDatabase() {
        global $wpdb;

        // prepare container
        $container = new EECF_Container_CustomFields('Test Container');
        $container->setup();
        $container->add_fields(array(
            EECF_Field::factory('repeater', 'repeater')->add_fields(array(
                EECF_Field::factory('text', 'field1'),
                EECF_Field::factory('text', 'field2'),
            )),
        ));

        // Prepare POST
        $_POST = array(
            '_repeater' => array(
                '0' => array(
                    '_field1' => 'Lorem ipsum',
                    '_field2' => 'Dolor sit amet',
                ),
            ),
        );

        // execute
        $container->save(123);

        // check field
        $db_values = $wpdb->get_results('
            SELECT meta_key, meta_value FROM ' . $wpdb->postmeta . '
            WHERE post_id="123" AND meta_key LIKE "_repeater%"
            ORDER BY meta_key
        ', ARRAY_A);

        $this->assertCount(2, $db_values);
        $this->assertEquals($db_values[0]['meta_key'], '_repeater_field1_0');
        $this->assertEquals($db_values[0]['meta_value'], 'Lorem ipsum');
        $this->assertEquals($db_values[1]['meta_key'], '_repeater_field2_0');
        $this->assertEquals($db_values[1]['meta_value'], 'Dolor sit amet');

        // cleanup
        $container->detach();
    }

    /**
     * @group slow
     */
    public function testSaveRepeaterAndCheckLoad() {
        global $wpdb;
        // prepare container
        $container = new EECF_Container_CustomFields('Test Container');
        $container->setup();
        $container->add_fields(array(
            EECF_Field::factory('repeater', 'repeater')->add_fields(array(
                EECF_Field::factory('text', 'field1'),
                EECF_Field::factory('text', 'field2'),
            )),
        ));

        // Prepare POST
        $_POST = array(
            '_repeater' => array(
                '0' => array(
                    '_field1' => 'Lorem ipsum',
                    '_field2' => 'Dolor sit amet',
                ),
            ),
        );

        // execute
        $container->save(123);
        $container->load();

        // check field
        $fields = $container->get_fields();
        $repeater_values = $fields[0]->get_values();

        $this->assertGreaterThanOrEqual(1, count($repeater_values));
        $repeater_values = $repeater_values[0];

        $expected_values = array(
            array('_field1', 'Lorem ipsum'),
            array('_field2', 'Dolor sit amet'),
        );

        $this->assertCount(2, $repeater_values);

        foreach ($expected_values as $index => $expected) {
            $this->assertEquals($repeater_values[$index]->get_name(), $expected[0]);
            $this->assertEquals($repeater_values[$index]->get_value(), $expected[1]);
        }

        // cleanup
        $container->detach();
    }

    /**
     * @group slow
     */
    public function testSaveGroupAndCheckDatabase() {
        global $wpdb;
        
        // prepare container
        $container = new EECF_Container_CustomFields('Test Container');
        $container->setup();
        $container->add_fields(array(
            EECF_Field::factory('groups', 'group')->add_fields(array(
                    EECF_Field::factory('text', 'field1'),
                    EECF_Field::factory('text', 'field2'),
                ), 'group1')->add_fields(array(
                    EECF_Field::factory('text', 'field3'),
                    EECF_Field::factory('text', 'field4'),
                ), 'group2'),
        ));

        // Prepare POST
        $_POST = array(
            '_group' => array(
                '0' => array(
                    'group' => '_group1',
                    '_field1' => 'lorem',
                    '_field2' => 'ipsum',
                    '_field3' => 'dolor',
                    '_field4' => 'sit',
                ),
                '1' => array(
                    'group' => '_group2',
                    '_field1' => 'lorem',
                    '_field2' => 'ipsum',
                    '_field3' => 'dolor',
                    '_field4' => 'sit',
                ),
            ),
        );

        // execute
        $container->save(123);

        // check field
        $db_values = $wpdb->get_results('
            SELECT meta_key, meta_value FROM ' . $wpdb->postmeta . '
            WHERE post_id="123" AND meta_key LIKE "_group%"
            ORDER BY meta_key
        ', ARRAY_A);

        $expected_values = array(
            array('_group_group1_field1_0', 'lorem'),
            array('_group_group1_field2_0', 'ipsum'),
            array('_group_group2_field3_1', 'dolor'),
            array('_group_group2_field4_1', 'sit'),
        );

        $this->assertCount(4, $db_values);

        foreach ($expected_values as $index => $expected) {
            $this->assertEquals($db_values[$index]['meta_key'], $expected[0]);
            $this->assertEquals($db_values[$index]['meta_value'], $expected[1]);
        }

        // cleanup
        $container->detach();
    }

    /**
     * @group slow
     */
    public function testSaveGroupAndCheckLoad() {
        global $wpdb;
        
        // prepare container
        $container = new EECF_Container_CustomFields('Test Container');
        $container->setup();
        $container->add_fields(array(
            EECF_Field::factory('groups', 'group')->add_fields(array(
                    EECF_Field::factory('text', 'field1'),
                    EECF_Field::factory('text', 'field2'),
                ), 'group1')->add_fields(array(
                    EECF_Field::factory('text', 'field3'),
                    EECF_Field::factory('text', 'field4'),
                ), 'group2'),
        ));

        // Prepare POST
        $_POST = array(
            '_group' => array(
                '0' => array(
                    'group' => '_group1',
                    '_field1' => 'lorem',
                    '_field2' => 'ipsum',
                    '_field3' => 'dolor',
                    '_field4' => 'sit',
                ),
                '1' => array(
                    'group' => '_group2',
                    '_field1' => 'lorem',
                    '_field2' => 'ipsum',
                    '_field3' => 'dolor',
                    '_field4' => 'sit',
                ),
            ),
        );

        // execute
        $container->save(123);
        $container->load();

        // check field
        $fields = $container->get_fields();
        $group_fields = $fields[0]->get_values();

        $expected_values = array(
            '_group1' => array(
                array('_field1', 'lorem'),
                array('_field2', 'ipsum'),
            ),
            '_group2' => array(
                array('_field3', 'dolor'),
                array('_field4', 'sit'),
            ),
        );

        $index = 0;

        $this->assertCount(2, $group_fields);
        foreach ($expected_values as $exp_group => $exp_group_values) {
            $group_values = $group_fields[$index];
            $this->assertEquals($group_values['type'], $exp_group);
            unset($group_values['type']);

            foreach ($exp_group_values as $subindex => $expected) {
                $this->assertEquals($group_values[$subindex]->get_name(), $expected[0]);
                $this->assertEquals($group_values[$subindex]->get_value(), $expected[1]);
            }
            $index++;
        }

        // cleanup
        $container->detach();
    }
}

