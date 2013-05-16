<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Newsletters
 *
 * @package		Newsletters
 * @author		Jerel Unruh
 * @copyright	Copyright (c) 2011 - 2013, Jerel Unruh (http://jerel.co/)
 * @license		http://www.apache.org/licenses/LICENSE-2.0.html (Apache 2)
 * @link		http://github.com/jerel/newsletters
 */

class Newsletters extends Admin_Controller
{
	public function __construct()
	{
		parent::__construct();
		
		$this->load->model('newsletters_m');
		$this->load->model('templates_m');
		$this->lang->load('newsletter');

		//set validation rules
		$this->load->library('form_validation');
		$this->newsletter_rules = array(
					array(
						'field' => 'title',
						'label' => lang('newsletters.title_label'),
						'rules'	=> 'trim|required|callback__tracked_urls'
						//validate the tracked urls array here to keep the callback
						//from getting called on every input field
					),
					array(
						'field' => 'read_receipts',
						'label' => lang('newsletters.title_newsletter_opens'),
						'rules'	=> 'trim|numeric'
					),
					array(
						'field' => 'body',
						'label' => lang('newsletters.body_label'),
						'rules' => 'trim|required'
					)
				);

		// determine the modules location once for use in js
		if(is_dir(APPPATH.'modules/newsletters'))
		{
			$this->template->append_metadata('<script type="text/javascript">var MODULE_LOCATION = "'.APPPATH.'modules/newsletters/";</script>');
		}
		elseif(is_dir(ADDONPATH.'modules/newsletters'))
		{
			$this->template->append_metadata('<script type="text/javascript">var MODULE_LOCATION = "'.ADDONPATH.'modules/newsletters/";</script>');
		}
		else
		{
			$this->template->append_metadata('<script type="text/javascript">var MODULE_LOCATION = "'.SHARED_ADDONPATH.'modules/newsletters/";</script>');
		}
	}
	
	// List all newsletters
	public function index()
	{
		// Create pagination links
		$total_rows = $this->newsletters_m->count_newsletters();

		$data = new StdClass;
		$data->pagination = create_pagination('admin/newsletters/index', $total_rows);

		// Using this data, get the relevant results
		$data->newsletters = $this->newsletters_m->get_newsletters( array('order'=>'created_on DESC',
																			   'limit' => $data->pagination['limit']) );
		
		$this->template->title($this->module_details['name'], lang('newsletters.templates'))
						->set('active_section', 'newsletters')
						->append_js('module::functions.js')
						->append_css('module::admin.css')
						->build('admin/index', $data);
	}

	//preview the newsletter without sending it
	public function view($id)
	{
		$data = new StdClass;
		$data->newsletter =  $this->newsletters_m->get_newsletter($id, $data);
		
		$this->template->set_layout('modal', 'admin')
					   ->append_js('module::functions.js')
					   ->build('admin/view', $data);
	}

	public function create()
	{
		$newsletter = new StdClass;
		$newsletter->tracked_urls = '';
		
		$this->form_validation->set_rules($this->newsletter_rules);
		
		if ( $this->form_validation->run() )
		{
			if( $this->newsletters_m->create_newsletter($this->input->post()) )
			{
				$this->session->set_flashdata('success', sprintf(lang('newsletters.add_success'), $this->input->post('title')));
				redirect('admin/newsletters');
			}
			else
			{
				$this->session->set_flashdata(array('error'=> lang('newsletters.add_error')));
			}
		}
		
		//get all of the templates
		$template_list = $this->templates_m->get_templates();
		$data->template_list[0] = '';
		foreach($template_list as $template)
		{
			$data->template_list[$template->id] = $template->title;
		}

		// Loop through each rule
		foreach($this->newsletter_rules as $rule)
		{
			$newsletter->{$rule['field']} = $this->input->post($rule['field']);
		}
		
		//populate the tracked url fields if there was an error
		if($input = $this->input->post('tracked_urls'))
		{			
			//key = target url, value = hash tag
			$combined_urls = array_combine($input['target'], $input['hash']);
			
			//remove the last element if it's empty
			if(end($combined_urls) == '')
			{
				array_pop($combined_urls);
			}
			
			foreach($combined_urls as $key => $value)
			{
				$newsletter->tracked_urls[$key] = $value;
			}
		}

		$data->newsletter =& $newsletter;
		$this->template->set('active_section', 'newsletters')
						->append_metadata( $this->load->view('fragments/wysiwyg', $data, TRUE) )
					   ->append_js('module::functions.js')
					   ->append_css('module::admin.css')
					   ->build('admin/create', $data);
	}

	public function edit($id)
	{
		$newsletter = new StdClass;
		$newsletter->tracked_urls = '';
		
		$this->form_validation->set_rules($this->newsletter_rules);
		
		if( $this->form_validation->run() )
		{
			if( $this->newsletters_m->edit_newsletter($this->input->post(), $id) )
			{
				$this->session->set_flashdata('success', sprintf(lang('newsletters.add_success'), $this->input->post('title')));
				redirect('admin/newsletters');
			}
			else
			{
				$this->session->set_flashdata(array('error'=> lang('newsletters.add_error')));
			}
		}
		
		//populate the tracked url fields if there was an error
		if($input = $this->input->post('tracked_urls'))
		{			
			//key = target url, value = hash tag
			$combined_urls = array_combine($input['target'], $input['hash']);
			
			//remove the last element if it's empty
			if(end($combined_urls) == '')
			{
				array_pop($combined_urls);
			}
			
			foreach($combined_urls as $key => $value)
			{
				$newsletter->tracked_urls[$key] = $value;
			}
		}
		
		$data = new StdClass;
		$data->newsletter = $this->newsletters_m->get_newsletter_source($id);
		$data->newsletter->tracked_urls = $newsletter->tracked_urls;
		$data->urls		= $this->newsletters_m->get_newsletter_urls($id);

		// Load WYSIWYG editor
		$this->template->set('active_section', 'newsletters')
						->append_metadata( $this->load->view('fragments/wysiwyg', $data, TRUE) )
					   ->append_js('module::functions.js')
					   ->append_css('module::admin.css')
					   ->build('admin/edit', $data);
	}

	public function delete($id)
	{
		if( $this->newsletters_m->delete_newsletter($id) )
		{
			redirect('admin/newsletters');
		}
		$this->session->set_flashdata(array('error'=> lang('newsletters.add_error')));

		redirect('admin/newsletters');

	}

	public function send()
	{
		if($this->settings->newsletter_cron_enabled == 1)
		{
			if($this->newsletters_m->set_cron($this->input->post('id')))
			{
				echo json_encode(array('message' => lang('newsletters.cron_set'), 'status' => 'Finished'));
			}
		}
		else
		{
			//send a copy of $data along for the parser
			$data =& $data;
			
			// Spit out the results for jQuery to pick up
			$status = $this->newsletters_m->send_newsletter($this->input->post('id'), $this->input->post('batch'), $data);
			
			echo json_encode($status);
		}
	}
	
	//show how many recipients opened their email, clicked on links, etc.
	public function statistics($id)
	{
		if($id)
		{
			$statistics = $this->newsletters_m->get_statistics($id);
			
			$data->statistics =& $statistics;
			$this->template->append_js('module::functions.js')
						   ->append_css('module::admin.css')
						   ->build('admin/statistics', $data);
		}
		else
		{
			redirect('admin/newsletters');
		}
	}
	
	//validate the Tracked URL fields
	public function _tracked_urls()
	{		
		foreach($this->input->post('tracked_urls') AS $key => $field)
		{
			//remove the hidden input fields that are there for jQuery's use
			array_pop($field);
			
			foreach($field AS $value)
			{
				//if field isn't empty, validate
				if($value > '')
				{
					$_POST[$key] = prep_url($value);
				}
			}
		}
	}
}