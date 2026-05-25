<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Items extends REST_Controller
{

	function __construct()
	{
		// Construct the parent class
		parent::__construct();
	}

	public function data_get($id = '')
	{
		if ($id != '') {
			$data = $this->Api_model->get_table('invoice_items', $id);
		} else {
			$search  = $this->input->get('search');
			$escaped_service = $this->db->escape_like_str($search);

			// Full-text search query
			$sql = "SELECT  
				i.rate, 
				i.id, 
				i.description as name, 
				i.long_description as subtext, 
				i.unit, 
				t1.taxrate as taxrate_1, 
				t2.taxrate as taxrate_2,
				t1.name as taxname_1,
				t2.name as taxname_2,
				t1.id as tax_id_1,
				t2.id as tax_id_2
				FROM " . db_prefix() . "items i
				LEFT JOIN " . db_prefix() . "taxes t1 ON t1.id = i.tax
				LEFT JOIN " . db_prefix() . "taxes t2 ON t2.id = i.tax2 
				WHERE MATCH (i.description) AGAINST ('$escaped_service' IN NATURAL LANGUAGE MODE)";
			// Execute the query
			$query = $this->db->query($sql);
			$data = $query->result_array();
		}

		$this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
	}
}
