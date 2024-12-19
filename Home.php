<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Quotation_model');
    }

   // Load the index view
public function index() {
    // Fetch quotations with their related items
    $this->db->select('quotations.*, GROUP_CONCAT(items.product_name) as product_name, GROUP_CONCAT(items.product_desc) as product_desc, GROUP_CONCAT(items.product_price) as product_price')
        ->from('quotations')
        ->join('items', 'items.quotation_id = quotations.id', 'left') // Left join to include quotations even if no items are associated
        ->group_by('quotations.id'); // Group by quotation ID to avoid duplicate rows
    $data['quotations'] = $this->db->get()->result_array(); // Use result_array for multiple results
    
    // echo "<pre>";
    // print_r($data['quotations']); 
    // exit;

    // Load the index view with data
    $this->load->view('index', $data); // Ensure the 'index' view exists and is set up
}


    public function create_quotation() {
        $data['termcondition'] = $this->db->where('status',1)->get('terms_conditions')->row_array();
        $this->load->view('create-quotation',$data); // Ensure the 'index' view exists
    }

    public function view_quotation(){
        $this->load->view('view-quotation');
    }

    // Save quotation via AJAX
    public function save_quotation() {
        // Decode the JSON data received via AJAX
        $data = json_decode($this->input->raw_input_stream, true);
       log_message('debug', 'Received data: ' . print_r($data, true));

        if (empty($data)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input data.']);
            return;
        }

        // Validate client and items data
        if (empty($data['client']) || empty($data['items'])) {
            echo json_encode(['status' => 'error', 'message' => 'Client or items data is missing.']);
            return;
        }

        // Extract client and items data
        $client = $data['client'];
        $items = $data['items'];

        // Input validation for client data
        if (empty($client['name']) || empty('term_conditon') || empty($client['address']) || !is_numeric($client['grand_total'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid client data.']);
            return;
        }

        // Validate items array
        foreach ($items as $item) {
            if (empty($item['product_name']) || empty($item['product_desc']) || !is_numeric($item['product_qty']) || !is_numeric($item['product_price'])) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid item data.']);
                return;
            }
        }

        // Save quotation and items in the database
        $quotation_id = $this->Quotation_model->saveQuotation($client, $items);

        if ($quotation_id) {
            echo json_encode(['status' => 'success', 'quotation_id' => $quotation_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save quotation.']);
        }
    }

    public function fetch_data() {
        // Fetch all quotations with company data
        $this->db->select('
            quotations.*,
            company.company_brand,
            company.company_email,
            company.company_logo,
            company.company_address
        ');
        $this->db->from('quotations');
        $this->db->join('company', '1=1', 'left'); // Join company table globally
        $quotations = $this->db->get()->result_array();
    
        $result = [];
    
        if (!empty($quotations)) {
            foreach ($quotations as $quotation) {
                // Fetch items for the current quotation
                $this->db->select('product_name, product_desc, product_qty, product_price');
                $this->db->from('items');
                $this->db->where('quotation_id', $quotation['id']);
                $items = $this->db->get()->result_array();
    
                // Add the quotation and its related items to the result array
                $quotation['items'] = $items; // Attach items to the current quotation
                $result[] = $quotation;      // Push the full quotation data with items
            }
    
            // Return the structured data
            echo json_encode(['status' => 'success', 'data' => $result]);
        } else {
            // Return an error response if no data is found
            echo json_encode(['status' => 'error', 'message' => 'No data found.']);
        }
    }
    
    
    



    public function print_quotation($id) {
        // Fetch terms and conditions from the database
        $term = $this->db->where('term_id',$id)->get('terms_conditions')->row_array();
    
        // Fetch the quotation details from the database
        $quotation = $this->Quotation_model->getQuotationById($id); 
        if (empty($quotation)) {
            show_error('Quotation not found');
        }
    
        // Fetch the items related to the quotation
        $items = $this->Quotation_model->getItemsByQuotationId($id);
    
        // Fetch the company details (assuming only one row in the 'company' table)
        $company = $this->db->get('company')->row_array();
    
        // Prepare data to pass to the view
        $data = [
            'quotation' => $quotation,
            'items' => $items,
            'company' => $company,
            'term' => $term // Corrected here
        ];
    
        // Load the printable view
        $this->load->view('print_quotation', $data);
    }
    


    // Edit 

    public function edit($id) {
        // // Fetch the specific quotation
        // $this->db->select('*')
        //     ->from('quotations')
        //     ->join('items', 'items.quotation_id = quotations.id', 'left')
        //     ->where('quotations.id', $id);
        // $data['quotation'] = $this->db->get()->row_array(); // Fetch single record
    
        // // Fetch related items for the quotation
        // $this->db->select('*')
        //     ->from('items')
        //     ->where('quotation_id', $id);
        // $data['items'] = $this->db->get()->result_array();

        $data['quotation'] = $this->db->get_where('quotations', ['id' => $id])->row_array();

        // Fetch the items related to the quotation
        $data['items'] = $this->db->get_where('items', ['quotation_id' => $id])->result_array();
    
        // Load the edit form view
        $this->load->view('edit', $data);
    }


    public function update() {
        $quotation_id = $this->input->post('quotation_id');
    $name = $this->input->post('name');
    $date = $this->input->post('date');
    $address = $this->input->post('address');

    // Update quotation details
    $quotation_data = [
        'name' => $name,
        'created_at' => $date,
        'address' => $address,
    ];
    $this->db->where('id', $quotation_id)->update('quotations', $quotation_data);

    // Update items (delete old and insert new)
    $this->db->where('quotation_id', $quotation_id)->delete('items');

    $product_names = $this->input->post('product_name');
    $product_qtys = $this->input->post('product_qty');
    $product_prices = $this->input->post('product_price');
    $product_descs = $this->input->post('product_desc');

    foreach ($product_names as $index => $product_name) {
        $item_data = [
            'quotation_id' => $quotation_id,
            'product_name' => $product_name,
            'product_qty' => $product_qtys[$index],
            'product_price' => $product_prices[$index],
            'product_desc' => $product_descs[$index],
        ];
        $this->db->insert('items', $item_data);
    }

    redirect('');
    }



    // Delete Qautation 

    public function delete() {
        $id = $this->input->post('id'); // Get the quotation ID from the request
        $response = ['success' => false, 'message' => ''];
    
        if (!empty($id)) {
            // Load your models (if not already loaded)
            $this->load->model('Quotation_model');
    
            // Start a transaction
            $this->db->trans_start();
    
            // Delete the quotation from the quotations table
            $this->Quotation_model->deleteQuotation($id);
    
            // Delete related items from the items table
            $this->Quotation_model->deleteQuotationItems($id);
    
            // Complete the transaction
            $this->db->trans_complete();
    
            if ($this->db->trans_status()) {
                $response['success'] = true;
                $response['message'] = 'Quotation and related items deleted successfully.';
            } else {
                $response['message'] = 'Failed to delete quotation and related items.';
            }
        } else {
            $response['message'] = 'Invalid quotation ID.';
        }
    
        // Return the response as JSON
        echo json_encode($response);
    }
    
    
    
    
}
