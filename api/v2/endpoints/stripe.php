<?php
include_once('assets/includes/stripe_config.php');
$pro_types_array = array(
                    1,
                    2,
                    3,
                    4
                );
if (empty($_POST['request']) || empty($_POST['token'])) {
	$error_code    = 4;
    $error_message = 'request and token can not be empty';
}
else{
	if (!empty($_POST['token']) && !empty($_POST['request']) && in_array($_POST['request'], array('wallet','fund','pro'))) {
		try {
			$db->where('user_id',$wo['user']['id'])->update(T_USERS,array('StripeSessionId' => ''));
			$checkout_session = \Stripe\Checkout\Session::retrieve($_POST['token']);
			if ($checkout_session->payment_status == 'paid') {
				$amount = ($checkout_session->amount_total / 100);
				if ($_POST['type'] == 'wallet') {
					$result = mysqli_query($sqlConnect, "UPDATE " . T_USERS . " SET `wallet` = `wallet` + " . $amount . " WHERE `user_id` = '" . $wo['user']['id'] . "'");
		            if ($result) {
		                $create_payment_log = mysqli_query($sqlConnect, "INSERT INTO " . T_PAYMENT_TRANSACTIONS . " (`userid`, `kind`, `amount`, `notes`) VALUES ('" . $wo['user']['id'] . "', 'WALLET', '" . $amount . "', 'stripe')");
		            }
					$response_data = array(
		                                'api_status' => 200,
		                                'message' => 'payment successfully'
		                            );
					echo json_encode($response_data, JSON_PRETTY_PRINT);
					exit();
				}
				if ($_POST['type'] == 'fund' && !empty($_POST['fund_id']) && is_numeric($_POST['fund_id']) && $_POST['fund_id'] > 0) {
					$fund_id = Wo_Secure($_POST['fund_id']);
					$fund = $db->where('id',$fund_id)->getOne(T_FUNDING);

				    if (!empty($fund)) {
				    	$notes = "Doanted to ".mb_substr($fund->title, 0, 100, "UTF-8");

				        $create_payment_log = mysqli_query($sqlConnect, "INSERT INTO " . T_PAYMENT_TRANSACTIONS . " (`userid`, `kind`, `amount`, `notes`) VALUES ({$wo['user']['user_id']}, 'DONATE', {$amount}, '{$notes}')");

				        $admin_com = 0;
			            if (!empty($wo['config']['donate_percentage']) && is_numeric($wo['config']['donate_percentage']) && $wo['config']['donate_percentage'] > 0) {
			                $admin_com = ($wo['config']['donate_percentage'] * $amount) / 100;
			                $amount = $amount - $admin_com;
			            }
				        $user_data = Wo_UserData($fund->user_id);
			            $db->where('user_id',$fund->user_id)->update(T_USERS,array('balance' => $user_data['balance'] + $amount));
			            $fund_raise_id = $db->insert(T_FUNDING_RAISE,array('user_id' => $wo['user']['user_id'],
			                                              'funding_id' => $fund_id,
			                                              'amount' => $amount,
			                                              'time' => time()));

			            $post_data = array(
			                'user_id' => Wo_Secure($wo['user']['user_id']),
			                'fund_raise_id' => $fund_raise_id,
			                'time' => time(),
			                'multi_image_post' => 0
			            );

			            $id = Wo_RegisterPost($post_data);

			            $notification_data_array = array(
		                    'recipient_id' => $fund->user_id,
		                    'type' => 'fund_donate',
		                    'url' => 'index.php?link1=show_fund&id=' . $fund->hashed_id
		                );
		                Wo_RegisterNotification($notification_data_array);
			            $response_data = array(
			                                'api_status' => 200,
			                                'message' => 'payment successfully'
			                            );
						echo json_encode($response_data, JSON_PRETTY_PRINT);
						exit();

				    }
				}
				elseif ($_POST['type'] == 'pro' && !empty($_POST['pro_type']) && in_array($_POST['pro_type'], $pro_types_array)) {
		            $is_pro = 0;
		            $stop   = 0;
		            $user   = Wo_UserData($wo['user']['user_id']);
		            if ($user['is_pro'] == 1) {
		                $stop = 1;
		                if ($user['pro_type'] == 1) {
		                    $time_ = time() - $star_package_duration;
		                    if ($user['pro_time'] > $time_) {
		                        $stop = 1;
		                    }
		                } else if ($user['pro_type'] == 2) {
		                    $time_ = time() - $hot_package_duration;
		                    if ($user['pro_time'] > $time_) {
		                        $stop = 1;
		                    }
		                } else if ($user['pro_type'] == 3) {
		                    $time_ = time() - $ultima_package_duration;
		                    if ($user['pro_time'] > $time_) {
		                        $stop = 1;
		                    }
		                } else if ($user['pro_type'] == 4) {
		                    if ($vip_package_duration > 0) {
		                        $time_ = time() - $vip_package_duration;
		                        if ($user['pro_time'] > $time_) {
		                            $stop = 1;
		                        }
		                    }
		                }
		            }
		            if ($stop == 0) {
		                $pro_types_array = array(
		                    1,
		                    2,
		                    3,
		                    4
		                );
		                $pro_type = $_POST['pro_type'];
		                $is_pro   = 1;
		            }
		            if ($stop == 0) {
		                $time = time();
		                if ($is_pro == 1) {
		                    $update_array   = array(
		                        'is_pro' => 1,
		                        'pro_time' => time(),
		                        'pro_' => 1,
		                        'pro_type' => $pro_type
		                    );
		                    if (in_array($pro_type, array_keys($wo['pro_packages_types'])) && $wo['pro_packages'][$wo['pro_packages_types'][$pro_type]]['verified_badge'] == 1) {
		                        $update_array['verified'] = 1;
		                    }
		                    $mysqli         = Wo_UpdateUserData($wo['user']['user_id'], $update_array);
		                    $notes              = $wo['lang']['upgrade_to_pro'] . " " . $img . " : Stripe";
		                    $create_payment_log = mysqli_query($sqlConnect, "INSERT INTO " . T_PAYMENT_TRANSACTIONS . " (`userid`, `kind`, `amount`, `notes`) VALUES ({$wo['user']['user_id']}, 'PRO', {$amount2}, '{$notes}')");
		                    $create_payment = Wo_CreatePayment($pro_type);
		                    if ($mysqli) {

		                        if ((!empty($_SESSION['ref']) || !empty($wo['user']['ref_user_id'])) && $wo['config']['affiliate_type'] == 1 && $wo['user']['referrer'] == 0) {
		                            if (!empty($_SESSION['ref'])) {
		                                $ref_user_id = Wo_UserIdFromUsername($_SESSION['ref']);
		                            }
		                            elseif (!empty($wo['user']['ref_user_id'])) {
		                                $ref_user_id = Wo_UserIdFromUsername($wo['user']['ref_user_id']);
		                            }


		                            if ($wo['config']['amount_percent_ref'] > 0) {
		                                if (!empty($ref_user_id) && is_numeric($ref_user_id)) {
		                                    $update_user    = Wo_UpdateUserData($wo['user']['user_id'], array(
		                                        'referrer' => $ref_user_id,
		                                        'src' => 'Referrer'
		                                    ));
		                                    $ref_amount     = ($wo['config']['amount_percent_ref'] * $amount1) / 100;
		                                    $update_balance = Wo_UpdateBalance($ref_user_id, $ref_amount);
		                                    unset($_SESSION['ref']);
		                                }
		                            } else if ($wo['config']['amount_ref'] > 0) {
		                                if (!empty($ref_user_id) && is_numeric($ref_user_id)) {
		                                    $update_user    = Wo_UpdateUserData($wo['user']['user_id'], array(
		                                        'referrer' => $ref_user_id,
		                                        'src' => 'Referrer'
		                                    ));
		                                    $update_balance = Wo_UpdateBalance($ref_user_id, $wo['config']['amount_ref']);
		                                    unset($_SESSION['ref']);
		                                }
		                            }
		                            
		                        }
		                        $response_data = array(
					                                'api_status' => 200,
					                                'message' => 'payment successfully'
					                            );
								echo json_encode($response_data, JSON_PRETTY_PRINT);
								exit();
		                    }
		                } else {
		                	$error_code    = 5;
						    $error_message = 'Something went wrong';
		                }
		            } else {
		                $error_code    = 6;
						$error_message = 'Something went wrong';
		            }
				}
			}
			else{
				$error_code    = 7;
				$error_message = 'wrong in payment';
			}
			
		} catch (Exception $e) {
			$error_code    = 8;
			$error_message = $e->getMessage();
		}
	}
	else{
		$error_code    = 4;
	    $error_message = 'request must be wallet , fund , pro';
	}
}



