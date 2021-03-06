<?php

##################################################
#
# Copyright (c) 2004-2006 OIC Group, Inc.
# Created by Adam Kessler @ 05/28/2008
#
# This file is part of Exponent
#
# Exponent is free software; you can redistribute
# it and/or modify it under the terms of the GNU
# General Public License as published by the Free
# Software Foundation; either version 2 of the
# License, or (at your option) any later version.
#
# GPL: http://www.gnu.org/licenses/gpl.txt
#
##################################################

class cartController extends expController {
	public $basemodel_name = 'order';
	private $checkout_steps = array('productinfo', 'specials', 'form', 'wizards', 'newsletter', 'confirmation', 'postprocess');
	//public $useractions = array('show'=>'Display Cart');
	public $useractions = array();

	function name() { return $this->displayname(); } //for backwards compat with old modules
	function displayname() { return "Ecommerce Shopping Cart"; }
	function description() { return "This is the cart users will add products from your store to."; }
	function author() { return "Adam Kessler @ OIC Group, Inc"; }
	function hasSources() { return true; }
	function hasViews() { return true; }
	function hasContent() { return true; }
	function supportsWorkflow() { return false; }

	function addItem() {
        global $router;
        
        $product_type = isset($this->params['product_type']) ? $this->params['product_type'] : 'product';
        $product = new product();
        
        //eDebug($this->params,true);
        //eDebug($this->params);
        //if we're trying to add a parent product ONLY, then we redirect to it's show view
        $c = null;
        if (isset($this->params['product_id']) && empty($this->params['children'])) $c = $product->find('first', 'parent_id=' . $this->params['product_id']);
        if (!empty($c->id)) 
        {
            flash('message', "Please select a product and quantity from the options listed below to add to your cart.");
            redirect_to(array('controller'=>'store','action'=>'show','id'=>$this->params['product_id']));      
        }
        
        //check for multiple product adding
        if (isset($this->params['prod-quantity'])) 
        {
            //we are adding multiple children, so we approach a bit different
            //we'll send over the product_id of the parent, along with id's and quantities of children we're adding
            
            foreach ($this->params['prod-quantity'] as $qkey=>&$quantity)
            {
                if (in_array($qkey,$this->params['prod-check']))
                {
                    //this might not be working...FJD
                    $child = new $product_type($qkey); 
                    if ($quantity < $child->minimum_order_quantity)                       
                    {
                        flash('message', $child->title . " - " . $child->model . " has a minimum order quantity of " . $child->minimum_order_quantity . 
                        '. Your quantity has been adjusted accordingly.');
                        $quantity = $child->minimum_order_quantity;
                        
                    }
                    $this->params['children'][$qkey] = $quantity;    
                }
                if (isset($child)) $this->params['product_id'] = $child->parent_id;                                                       
            }
           
        }         
         $product = new $product_type($this->params['product_id'], true, true);  //need true here?
        
        if (($product->hasOptions() || $product->hasUserInputFields()) && (!isset($this->params['options_shown']) || $this->params['options_shown']!= $product->id)) 
        {
            
            // if we hit here it means this product type was missing some
            // information it needs to add the item to the cart..so we need to help
            // it display its addToCart form
            /*redirect_to(array(
                    'controller'=>'cart',
                    'action'=>'displayForm',
                    'form'=>'addToCart',
                    'product_id'=>$this->params['product_id'],
                    'product_type'=>$this->params['product_type'],
                    'children'=>serialize($this->params['children']), 
            ));*/
            $product->displayForm('addToCart', $this->params);
            return false;
        }         
        //product either has no options, user input fields, or has already seen and passed the options page, so we try adding to cart
        //it will validate and fail back to the options page if data is incorrect for whatever reason (eg, bad form post)
        //eDebug($this->params, true);
        //$this->params['qty'] = 1; //REMOVE ME
        if ($product->addToCart($this->params))
        {
            if (empty($this->params['quick'])) {
                flash('message', "Added ".$product->title." to your cart. <a href='" . $router->makeLink(array('controller'=>'cart', 'action'=>'checkout'), false, true) ."'>Click here to checkout now.</a>");
                expHistory::back();
                //expHistory::lastNotEditable();
            } else {
                redirect_to(array('controller'=>'cart', 'action'=>'quickPay'));
            } 
        } 
    }
	    
	function updateQuantity() {
		global $order;
		if (exponent_javascript_inAjaxAction()) {
			$id = str_replace('quantity-', '', $this->params['id']);
            $item = new orderitem($id);
            if (!empty($item->id)) {
                $newqty = $item->product->updateQuantity($this->params['value']);
                if ($newqty > $item->product->quantity) {
                    if ($item->product->availability_type == 1) {
                        $diff = ($item->product->quantity <=0) ? $newqty : $newqty - $item->product->quantity;
                        $updates->message = 'Only '.$item->product->quantity.' '.$item->products_name.' are currently in stock. Shipping may be delayed on the other '.$diff;
                    } elseif ($item->product->availability_type == 2) {
                        $updates->message = $item->products_name.' only has '.$item->product->quantity.' on hand. You can not add any more to your cart.';
                        $updates->cart_total = '$'.number_format($order->getCartTotal(), 2);
			            $updates->item_total = '$'.number_format($item->getTotal(), 2);
			            $updates->item_id = $id;
			            $updates->quantity = $item->product->quantity;
			            echo json_encode($updates);
			            return true;
                    }
                }
			    $item->quantity = $newqty;
			    $item->save();
			    $order->refresh();
			    $updates->cart_total = '$'.number_format($order->getCartTotal(), 2);
			    $updates->item_total = '$'.number_format($item->getTotal(), 2);
			    $updates->item_id = $id;
			    $updates->quantity = $item->quantity;
			    echo json_encode($updates);
			}
		} else {            

            if (!is_numeric($this->params['quantity']))
            {
                flash('error', 'Please enter a valid quantity.');
                expHistory::back(); 
            }
            
            $item = new orderitem($this->params['id']);
            
            if (!empty($item->id)) {
                $newqty = $item->product->updateQuantity($this->params['quantity']);
                if ($newqty > $item->product->quantity) 
                {
                    if ($item->product->availability_type == 1) {
                        $diff = ($item->product->quantity <=0) ? $newqty : $newqty - $item->product->quantity;
                        flash('message', 'Only '.$item->product->quantity.' '.$item->products_name.' are currently in stock. Shipping may be delayed on the other '.$diff);
                        //$updates->message = 'Only '.$item->product->quantity.' '.$item->products_name.' are currently in stock. Shipping may be delayed on the other '.$diff;
                    } elseif ($item->product->availability_type == 2) {
                        flash('error', $item->products_name.' only has '.$item->product->quantity.' on hand. You can not add any more to your cart.');
                        /*$updates->message = $item->products_name.' only has '.$item->product->quantity.' on hand. You can not add any more to your cart.';                        
                        $updates->cart_total = '$'.number_format($order->getCartTotal(), 2);
                        $updates->item_total = '$'.number_format($item->quantity*$item->products_price, 2);
                        $updates->item_id = $id;
                        $updates->quantity = $item->product->quantity;
                        echo json_encode($updates);  */
                        expHistory::back();
                    }
                }
                else if ($newqty == 0)
                {
                    $item->delete();
                    flash('message', $item->products_name.' has been removed from your cart.');
                    expHistory::back();
                }
                $item->quantity = $newqty;
                $item->save();
                $order->refresh();    
                
                /*$updates->cart_total = '$'.number_format($order->getCartTotal(), 2);
                $updates->item_total = '$'.number_format($item->quantity*$item->products_price, 2);
                $updates->item_id = $id;
                $updates->quantity = $item->quantity;      */
                //echo json_encode($updates);
            }
			//redirect_to(array('controller'=>'cart','action'=>'show'));
            flash('message', $item->products_name.' quantity has been updated.');
            expHistory::back();
		}
	}
	
	function removeItem() {
		global $order;
		foreach ($order->orderitem as $item) {
			if ($item->id == intval($this->params['id'])) {
			    $product = new  $item->product_type($item->product_id);
			    $product->removeItem($item);
				$item->delete();
			}
		}

		expHistory::back();
	}

	function show() {
		global $order;
		expHistory::set('viewable', $this->params);
		assign_to_template(array('items'=>$order->orderitem, 'order'=>$order));
	}
	
	function checkout() {
		global $user, $order;
                
        $cfg->mod = "cart";
        $cfg->src = "@globalcartsettings";
        $cfg->int = "";
        $config = new expConfig($cfg);
                
        if($order->total<intval($config->config['min_order'])){
            flashAndFlow('error', "The minimum cart amount is $".number_format($config->config['min_order'],2,".",",").". Keep Shopping!");
        }

		if (!exponent_sessions_get("ALLOW_ANONYMOUS_CHECKOUT") && !exponent_users_isLoggedIn()) {
		    expHistory::set('viewable', $this->params);
			flash('message', "Please select how you would like to continue with the checkout process.");
			exponent_flow_redirecto_login(makeLink(array('module'=>'cart','action'=>'checkout'), 'secure'));
		}

		if (empty($order->orderitem)) flashAndFlow('error', 'There are no items in your cart.');

        $billing = new billing();
        //eDebug($billing,true);
        if (count($billing->available_calculators) < 1) {
            flashAndFlow('error', 'This store is not configured to allow checkouts yet.  Please try back soon.');
        }
        
        // set a flow waypoint
		expHistory::set('viewable', $this->params);
		
        //this validate the discount codes already applied to make sure they are still OK
        //if they are not it will remove them and redirect back to checkout w/ a message flash
        $order->updateOrderDiscounts();
        
        //eDebug($order);
        // are there active discounts in the db?
        $discountCheck = new discounts();
        $discountsEnabled = $discountCheck->find('all','enabled=1');
        if (empty($discountsEnabled)) {
            // flag to hide the discount box
            assign_to_template(array('noactivediscounts'=>'1'));
            $discounts = null;
        } else {
            // get all current discount codes that are valid and applied
    		$discounts = $order->getOrderDiscounts();
        }
        //eDebug($discounts);
		/*if (count($discounts)>=0) {
		    // Mockup code
		    $order->totalBeforeDiscounts = $order->total; // reference to the origional total
		    $order->total = $order->total*85/100; // to simulate 15%
		    
		} */
		// call each products checkout() callback & calculate total
		foreach($order->orderitem as $item) {
			$product = new $item->product_type($item->product_id);
			$product->checkout();
		}

		// get the specials...this is just a stub function for now.
		$specials = $this->getSpecials();	

		// get all the necessary addresses..shipping, billing, etc
		$address = new address();
		//$addresses_dd = $address->dropdownByUser($user->id);
		$shipAddress = $address->find('first', 'user_id='.$user->id . ' AND is_shipping=1');
		if (empty($shipAddress)) {
		    flash('message', 'Step One: enter your primary address info now. 
            <br><br>You may also optionally provide a password if you would like to return to our store at a later time to view your order history or 
            make additional purchases.
		    <br><br>
		    If you need to add another billing or shipping address you will be able to do so on the following page.
		    ');
		    redirect_to(array('controller'=>'address','action'=>'edit'));
		}

		// get the shipping calculators and the shipping methods if we need them
		$shipping = new shipping();
        //$shipping->shippingmethod->setAddress($shipAddress);
        
        $shipping->getRates();

		assign_to_template(array(
                    'cartConfig'=>$config->config,
        			//'addresses_dd'=>$addresses_dd,
					//'addresses'=>$addresses,
					'shipping'=>$shipping,
					'user'=>$user,
					'billing'=>$billing,
					'discounts'=>$discounts,
					'order'=>$order,
					//'needs_address'=>$needs_address,
		));
	}
    
    /**
    * the first thing after checkout.
    * 
    */
    public function preprocess()
    {
        //eDebug($this->params,true);
        global $order, $user, $db;
        
        //eDebug($_POST, true);
        // get the shippnig and billing objects, these objects handle the setting up the billing/shipping methods
        // and their calculators
        $shipping = new shipping();
        $billing = new billing();
        // since we're skipping the billing method selection, do it here
        $billing->billingmethod->update($this->params);
        //this is just dumb. it doesn't update the object, refresh doesn't work, and I'm tired
        $billing = new billing();
                            
        if (!$user->isLoggedIn()) 
        {
            flash('message', "It appears that your session has expired. Please log in to continue the checkout process.");
            exponent_flow_redirecto_login(makeLink(array('module'=>'cart','action'=>'checkout'), 'secure'));
        }
        
        // Make sure all the pertanent data is there...otherwise flash an error and redirect to the checkout form.
        if (empty($order->orderitem)) 
        {
            flash('error', 'There are no items in your cart.');
        }
        if (empty($shipping->calculator->id) && !$shipping->splitshipping)
        {
            flash('error', 'You must pick a shipping method');
        }
        if (empty($shipping->address->id) && !$shipping->splitshipping)
        {
            flash('error', 'You must pick a shipping address');
        }
        if (empty($billing->calculator->id))
        {
            flash('error', 'You must pick a billing method'); 
        }
        if (empty($billing->address->id)) 
        {
            flash('error', 'You must select a billing address');
        }
        
        // make sure all the methods picked for shipping meet the requirements
        foreach ($order->getShippingMethods() as $smid) 
        {
            $sm = new shippingmethod($smid);
            $calcname = $db->selectValue('shippingcalculator', 'calculator_name', 'id='.$sm->shippingcalculator_id);
            $calc = new $calcname($sm->shippingcalculator_id);
            $ret = $calc->meetsCriteria($sm);
            if (is_string($ret)) 
            {
                flash('error', $ret);
            }
        }
        
        // if we encounterd any errors we will return to the checkout page and show the errors
        if (!expQueue::isQueueEmpty('error'))
        {
            redirect_to(array('controller'=>'cart', 'action'=>'checkout'));
        }
             
        // get the billing options..this is usually the credit card info entered by the user
        $opts = $billing->calculator->userFormUpdate($this->params);
        exponent_sessions_set('billing_options', $opts);
        //eDebug($opts,true);
        // final the cart totals       
        $order->calculateGrandTotal(); 
        //eDebug($order,true);
        // call the billing mehod's preprocess in case it needs to prepare things.
       // eDebug($billing);
        $result = $billing->calculator->preprocess($billing->billingmethod, $opts, $this->params);
        //eDebug($result, true);
        if (empty($result->errorCode)) 
        {
            redirect_to(array('controller'=>'cart', 'action'=>'confirm'));
        } 
        else 
        {
            flash('error', 'An error was encountered while processing your transaction.<br /><br />'.$result->message);
            expHistory::back();
        }
    }

	public function confirm() 
    {
        global $order, $user, $db;
        
        // final the cart totals       
        $order->calculateGrandTotal(); 
              
        //eDebug($order);
        // get the shippnig and billing objects, these objects handle the setting up the billing/shipping methods
        // and their calculators
        $shipping = new shipping();
        $billing = new billing();
        
        $opts = exponent_sessions_get('billing_options');
        
		assign_to_template(array(
		    'shipping'=>$shipping, 
		    'billing'=>$billing, 
		    'order'=>$order,
		    'total'=>$order->total, 
		    'billinginfo'=>$billing->calculator->userView($opts),
		));
	}
    
	public function process() {
		global $db, $order, $user;
        
        
		if (!$user->isLoggedIn() && empty($this->params['nologin'])) {
            flash('message', "It appears that your session has expired. Please log in to continue the checkout process.");
            expHistory::back();
            
            //exponent_flow_redirecto_login(makeLink(array('module'=>'cart','action'=>'checkout'), 'secure'));
        }
		// if this error hits then something went horribly wrong or the user has tried to hit this 
		// action themselves before the cart was ready or is refreshing the page after they've confirmed the 
		// order.
		if (empty($order->orderitem)) flash('error', 'There are no items in your cart.');
		if (!expQueue::isQueueEmpty('error')) redirect_to(array('controller'=>'store', 'action'=>'showall'));
		
		// set the gift comments
		$order->update($this->params);
		
		// get the biling & shipping info
		$shipping = new shipping();
		$billing = new billing();

        // finalize the total to bill
        $order->calculateGrandTotal();
        
		// call the billing calculators process method - this will handle saving the billing options to the database.
		$result = $billing->calculator->process($billing->billingmethod, exponent_sessions_get('billing_options'), $this->params);

        if ($result->errorCode == 0) {
            // save out the cart total to the database		
		    $billing->billingmethod->update(array('billing_cost'=>$order->grand_total));

		    // set the invoice number and purchase date in the order table..this finializes the order
		    //$invoice_num = $db->max('orders', 'invoice_id') + 1;
		    //if ($invoice_num < ecomconfig::getConfig('starting_invoice_number')) $invoice_num += ecomconfig::getConfig('starting_invoice_number');
		    
		    // get the first order status and set it for this order
		    $order->update(array('invoice_id'=>$order->getInvoiceNumber(), 'purchased'=>time(), 'updated'=>time(), 'comment'=>serialize($comment)));
		    $order->setDefaultStatus();
            $order->setDefaultOrderType();
		    $order->refresh();
		
            // run each items process callback function
		    foreach($order->orderitem as $item) {
		        $product = new $item->product_type($item->product_id);
		        $product->process($item);
		    }
		
            $billing->calculator->postProcess();
            
        } else {
            flash('error', 'An error was encountered while processing your transaction.<br /><br />'.$result->message);
            expHistory::back();
            
            //redirect_to(array('controller'=>'cart', 'action'=>'checkout'));
        }
        
        $billinginfo = $billing->calculator->userView(unserialize($billing->billingmethod->billing_options));
        
        // send email invoices to the admins & users if needed
        $invoice = renderAction(array('controller'=>'order', 'action'=>'email', 'id'=>$order->id));
        
		//assign_to_template(array('order'=>$order, 'billing'=>$billing, 'shipping'=>$shipping, 'result'=>$result, 'billinginfo'=>$billinginfo));
		flash('message', 'Your order has been submitted.');
        redirect_to(array('controller'=>'order', 'action'=>'myOrder', 'id'=>$order->id));
	}
	
	function quickPay() {
	    global $order, $user;
	    
	    if ($order->shipping_required) redirect_to(array('controller'=>'cart', 'action'=>'checkout'));
	    if (empty($order->orderitem)) flashAndFlow('error', 'There are no items in your cart.');
		
	    // if we made it here it means that the item was add to the cart. 
		expHistory::set('viewable', $this->params);
		
		// call each products checkout() callback & calculate total
		foreach($order->orderitem as $item) {
			$product = new $item->product_type($item->product_id);
			$product->checkout();
		}
		
		// setup the billing & shipping calculators info
		if ($product->requiresBilling) {
    		$billing = new billing();
    		assign_to_template(array('billing'=>$billing));
        }
		
		if ($product->requiresShipping) {		    
		    $shipping = new shipping();		
		    $shipping->pricelist = $shipping->listPrices();
		    assign_to_template(array('shipping'=>$shipping));
		}
		
		assign_to_template(array('product'=>$product, 'user'=>$user, 'order'=>$order));
	}
	
	function processQuickPay() {
	    global $order, $template;
	    
	    // reuse the confirm action's template
	    $template = get_template_for_action($this, 'confirm', $this->loc);
	    
	    if (!empty($this->params['billing'])) {
	        $billing = new billing();
    		$billing->billingmethod->setAddress($this->params['billing']);
		}
		
		if (!empty($this->params['shipping'])) {
		    die('NEED TO IMPLEMENT THE SHIPPING PIECE!!');
		    $shipping = new shipping();
    		$shipping->shippingingmethod->setAddress($this->params['shipping']);
    		assign_to_template(array('shipping'=>$shipping));
		}
		
		$opts = $billing->calculator->userFormUpdate($this->params);
		$order->calculateGrandTotal();
		exponent_sessions_set('billing_options', $opts);
		assign_to_template(array(
		    'billing'=>$billing, 
		    'order'=>$order,
		    'total'=>$order->total, 
		    'billinginfo'=>$billing->calculator->userView($opts),
		    'nologin'=>1
		));
	}
	
    public function splitShipping() {
        global $user, $order;        

        expHistory::set('viewable', $this->params);        
        
        // get all the necessary addresses..shipping, billing, etc
		$address = new address(null, false, false);
		$addresses_dd = $address->dropdownByUser($user->id);
		
		if (count($addresses_dd) < 2) {
		    expHistory::set('viewable', $this->params);        
		    flash('error', 'You must have more than 1 address to split your shipment.  Please add another now.');
		    redirect_to(array('controller'=>'address','action'=>'edit'));
		}
		
		// setup the list of orderitems
		$orderitems = array();
		foreach($order->orderitem as $item) {
		    if ($item->product->requiresShipping == true) {
		        for($i=0; $i < $item->quantity; $i++) {
		            $orderitems[] = $item;
		        }
		    }
		}

        if (count($orderitems) < 2) {
            flashAndFlow('error', 'You must have a minimum of 2 items in your shopping cart in order to split your shipment.');
        }
        
        expHistory::set('viewable', $this->params);        
		assign_to_template(array('addresses_dd'=>$addresses_dd,'orderitems'=>$orderitems, 'order'=>$order));    
    }
    
    public function saveSplitShipping() {
        global $db;
        $addresses = array();
        $orderitems_to_delete = '';
        
        foreach ($this->params['orderitems'] as $id=>$address_ids) {            
            foreach($address_ids as $address_id) {
                if (empty($addresses[$address_id][$id])) {
                    $addresses[$address_id][$id] = 1;
                } else {
                    $addresses[$address_id][$id]+= 1;
                }
            } 
            
            if (!empty($orderitems_to_delete)) $orderitems_to_delete .= ',';
            $orderitems_to_delete .= $id;
        }
        
        foreach($addresses as $addy_id => $orderitems) {   
            $shippingmethod = new shippingmethod();
            $shippingmethod->setAddress($addy_id);     

            foreach($orderitems as $orderitem_id => $qty) {
                $orderitem = new orderitem($orderitem_id);                
                unset($orderitem->id);
                unset($orderitem->shippingmethods_id);
                $orderitem->shippingmethods_id = $shippingmethod->id;                
                $orderitem->quantity = $qty;
                $orderitem->save();
            }
        }
        
        $db->delete('orderitems', 'id IN ('.$orderitems_to_delete.')');
        redirect_to(array('controller'=>'cart', 'action'=>'selectShippingMethods'));
    }
    
    public function selectShippingMethods() {
        global $order;
        
        expHistory::set('editable', $this->params);
        $shipping = new shipping();
        $shippingmethod_id = $order->getShippingMethods();

        $shipping_items = array();
        foreach ($shippingmethod_id as $id) {
            $shipping_items[$id]->method = new shippingmethod($id);
            $shipping_items[$id]->orderitem = $order->getOrderitemsByShippingmethod($id);
            foreach ($shipping_items[$id]->orderitem as $key=>$item) {
                if ($item->product->requiresShipping == false) {
                    unset($shipping_items[$id]->orderitem[$key]);
                }
            }            
            
            if (empty($shipping_items[$id]->orderitem)) {
                unset($shipping_items[$id]);
            } else {
                foreach ($shipping->available_calculators as $calcid=>$name) {
                    $calc = new $name($calcid);
                    $shipping_items[$id]->prices[$calcid] = $calc->getRates($shipping_items[$id]);
                    //eDebug($shipping_items[$id]->prices[$id]);
                }
            }
        }
        
        assign_to_template(array('shipping_items'=>$shipping_items, 'shipping'=>$shipping));
    }
    
    public function setAnonymousCheckout()
    {
        exponent_sessions_set('ALLOW_ANONYMOUS_CHECKOUT', true);        
        redirect_to(array('controller'=>'cart', 'action'=>'checkout'));
    }
    
    public function saveShippingMethods() {
        global $order;
        
        $shipping = new shipping();
        $order->shippingmethods = array();
        
        // if they didn't fill out anything
        if (empty($this->params['methods'])) {
            expValidator::failAndReturnToForm("You did not pick  any shipping options", $this->params);
        }
        
        // if they don't check all the radio buttons
        if (count($this->params['methods']) < count($this->params['calcs'])) {
            expValidator::failAndReturnToForm("You must select a shipping options for all of your packages.", $this->params);
        }
        
        foreach ($this->params['methods'] as $id=>$method) {
            $cost = $this->params['cost'][$method];
	        $title = $this->params['title'][$method];
            $shippingmethod = new shippingmethod($id);
            $shippingmethod->update(array(
                'option'=>$method,
                'option_title'=>$title,
                'shipping_cost'=>$cost, 
                'shippingcalculator_id'=>$this->params['calcs'][$id],
            ));

            $order->shippingmethods[] = $shippingmethod->id;
        }
         
        redirect_to(array('controller'=>'cart', 'action'=>'checkout'));
    }
    
	function createaddress() {
		global $db, $user;
		if ($user->isLoggedIn()) {
		    // save the address, make it default if it is the users first one
			$address = new address();
			$count = $address->find('count', 'user_id='.$user->id);
			if ($count == 0) $this->params['is_default'] = 1;
			$this->params['user_id'] = $user->id;
			$address->update($this->params);
			
			// set the billing/shipping method
			if (isset($this->params['addresstype'])) {
			    if ($this->params['addresstype'] == 'shipping') {
			        $shipping = new shipping();
			        $shipping->shippingmethod->setAddress($address);
			    } elseif ($this->params['addresstype'] == 'billing') {
			        $billing = new billing();
			        $billing->billingmethod->setAddress($address);
			    }
			}
			
		}

		redirect_to(array('controller'=>'cart', 'action'=>'checkout'));
	}

	function getSpecials() {
		//STUB::flesh this function out eventually.
		return null;
	}
	
	// Discount Codes
	
	function isValidDiscountCode($code) {
        // is the code valid?
        if ($code == '12345') {
            # psudocode:
            # grab current order discounts
            # $discounts = new discountCode($order);
            # append the new discount code to the current codes
            # $discounts->appendCode($code);
            
            return true;
        } else {
            return false;
        }
	}
	
	/*function checkDiscount() {
	    // handles what to do when a code valid or not
	    if (isValidDiscountCode($this->params['discountcode'])) {
    	    flash('message', "Discount Code Applied");
            redirect_to(array('controller'=>'cart', 'action'=>'checkout'));
	    } else {
    	    flash('error', "Sorry, the discount code provided is not a valid code.");
            redirect_to(array('controller'=>'cart', 'action'=>'checkout'));
	    }
	}   */
	
    function addDiscountToCart(){
        global $user, $order;
        //lookup discount to see if it's real and valid, and not already in our cart
        //this will change once we allow more than one coupon code
        
        $discount = new discounts();        
        $discount = $discount->getCouponByName($this->params['coupon_code']);
        
        if (empty($discount)) {
            flash('error', "This discount code you entered does not exist.");
            redirect_to(array('controller'=>'cart', 'action'=>'checkout'));       
        } 
        
        //check to see if it's in our cart already
        if ($this->isDiscountInCart($discount->id))
        {
            flash('error', "This discount code is already in your cart.");
            redirect_to(array('controller'=>'cart', 'action'=>'checkout'));
        }   
        
        //this should really be reworked, as it shoudn't redirect directly and not return
        $validateDiscountMessage = $discount->validateDiscount();
        if ($validateDiscountMessage == "")
        {
            //if all good, add to cart, otherwise it will have redirected
            $od = new order_discounts();
            $od->orders_id = $order->id;
            $od->discount_id = $discount->id;
            $od->coupon_code = $discount->coupon_code;
            $od->title = $discount->title;
            $od->body = $discount->body;
            $od->save();
            // set this to just the discount applied via this coupon?? if so, when though? $od->discount_total = ??;
            flash('message', "The discount code has been applied to your cart.");               
        }
        else
        {
            flash('error',$validateDiscountMessage);         
        }   
        redirect_to(array('controller'=>'cart', 'action'=>'checkout'));                           
    }
    
    function removeDiscountFromCart($id = null, $redirect = true){
        //eDebug($params);
        if ($id == null) $id = $this->params['id'];
        $od = new order_discounts($id);        
        $od->delete();
        flash('message', "The discount code has been removed from your cart");
        if ($redirect == true)
        {
            redirect_to(array('controller'=>'cart', 'action'=>'checkout'));
        }
    }  
                     
    function isDiscountInCart($discountId) 
    {
        global $order;
        $cds = $order->getOrderDiscounts();
        if (count($cds)==0) return false;
       
        foreach ($cds as $d)
        {
            if ($discountId == $d->discount_id) return true;
        }
        return false;
    }
    
    function configure() {
        expHistory::set('editable', $this->params);
        
        $this->loc->src = "@globalcartsettings";
        $config = new expConfig($this->loc);
        $this->config = $config->config;

        assign_to_template(array('config'=>$this->config));
    }    
    
	//this is ran after we alter the quantity of the cart, including
    //delete items or runing the updatequantity action
    private function rebuildCart()
    {
        //group items by type and id               
        //since we can have the same product in different items (options and quantity discount)
        //remove items and readd?
        global $order;
        //eDebug($order,true); 
        $items = $order->orderitem;                
        foreach ($order->orderitem as $item)
        {
            $item->delete();   
        }  
        $order->orderitem = array();
        $order->refresh();
        foreach ($items as $item)
        {
            
            for ($x=1; $x<=$item->quantity; $x++)
            {
                $product = $item->product;
                $price = $product->getBasePrice(); 
                $basePrice = $price;
                $options = array();
                if (!empty($item->opts)) {
                    foreach ($item->opts as $opt) {                    
                        $cost = $opt[2] == '$' ? $opt[4] :  $basePrice * ($opt[4] * .01);
                        $cost = $opt[3] == '+' ? $cost : $cost * -1;                      
                        $price += $cost;
                        $options[] = $opt;
                    }
                }
                $params['options'] = serialize($options);
                $params['products_price'] = $price;
                $params['product_id'] = $product->id;    
                $params['product_type'] = $product->product_type;                            
            
                $newitem = new orderitem($params);
                //eDebug($item, true);
                $newitem->products_price = $price;
                $newitem->options = serialize($options);
                
                $sm = $order->getCurrentShippingMethod();
                $newitem->shippingmethods_id = $sm->id;
                $newitem->save();
                $order->refresh();
            }            
        }
        $order->save();
        /*eDebug($items);
        
        
        $options = array();  
        foreach ($this->optiongroup as $og) {
            if ($og->required && empty($params['options'][$og->id][0])) {
                
                flash('error', $this->title.' requires some options to be selected before you can add it to your cart.');
                redirect_to(array('controller'=>store, 'action'=>'show', 'id'=>$this->id));
            }
            if (!empty($params['options'][$og->id])) {
                foreach ($params['options'][$og->id] as $opt_id) {
                    $selected_option = new option($opt_id);
                    $cost = $selected_option->modtype == '$' ? $selected_option->amount :  $this->getBasePrice() * ($selected_option->amount * .01);
                    $cost = $selected_option->updown == '+' ? $cost : $cost * -1;                      
                    $price += $cost;
                    $options[] = array($selected_option->id,$selected_option->title,$selected_option->modtype,$selected_option->updown,$selected_option->amount);
                }
            }
        }
        //die();
        // add the product to the cart.
        $params['options'] = serialize($options);
        $params['products_price'] = $price;
        $item = new orderitem($params);
        //eDebug($item, true);
        $item->products_price = $price;
        $item->options = serialize($options);
        
        $sm = $order->getCurrentShippingMethod();
        $item->shippingmethods_id = $sm->id;
        $item->save();                            */
        return true;
        
    }
    
}

?>
