<?php

require __DIR__ . '/vendor/autoload.php';

class KiteTicker 
{
    // Constant
    // Available streaming modes
    public $MODE_FULL = "full";
    public $MODE_QUOTE = "quote";
    public $MODE_LTP = "ltp";

    // Segment constants
    public $EXCHANGE_MAP = [
        "nse" => 1,
        "nfo" => 2,
        "cds" => 3,
        "bse" => 4,
        "bfo" => 5,
        "bcd" => 6,
        "mcx" => 7,
        "mcxsx" => 8,
        "indices" => 9,
    ];

    public function __construct(
        string $api_key,
        string $access_token, 
        string $subscribe_mode,
        array $subscribe_token,
        int $timeout = 30
    ){
        $this->api_key = $api_key;
        $this->access_token = $access_token;
        $this->ws_url = "wss://ws.kite.trade?api_key={$this->api_key}&access_token={$this->access_token}";
        $this->subscribe_mode = $subscribe_mode;
        $this->subscribe_token = $subscribe_token;
        $this->timeout = $timeout;
    }

    public function connect(){
        $react_connector = new React\Socket\Connector(['timeout' => $this->timeout]);
        $loop = React\EventLoop\Loop::get();
        $connector = new Ratchet\Client\Connector($loop, $react_connector);
        $connector($this->ws_url)->then(function(\Ratchet\Client\WebSocket $conn) {
        $conn->on('message', function(\Ratchet\RFC6455\Messaging\MessageInterface $msg) use ($conn) {
            var_dump($this->parseBinary($msg));
        });

        $conn->on('close', function ($code = null, $reason = null) {
            echo "Connection closed ({$code} - {$reason})\n";
        });

        $conn->on('error', function (Exception $e) {
            echo 'error: ' . $e->getMessage();
        });

        $json_ws_subscribe = json_encode($this->subscribeToken($this->subscribe_mode, $this->subscribe_token));
        $conn->send($json_ws_subscribe);

    }, function ($e) {
        echo "Could not connect: {$e->getMessage()}\n";
    });
    }

    // Subscribes for the given list of tokens
    function subscribeToken(string $mode, array $token){
        if($mode == "quote"){
            $subscribe_detail = array('a' => 'subscribe', 'v' =>$token);
        }
        else{
            $set_mode = array($mode, $token);
            $subscribe_detail = array('a' => 'mode', 'v' =>$set_mode);
        }
        return $subscribe_detail;
    }

    // Unpack binary data
    function unpackBinary(String $msg, string $byte_format, int $start, int $end)
    {
        $packet = unpack($byte_format, substr($msg, $start, $end));
        return $packet[1];
    }

    // Split the data to individual packets of ticks
    function splitPackets(String $msg)
    {
        // Ignore heartbeat data
        if (strlen($msg)<2){
            return [];
        }
        // Count of subscribed instrument i.e the number of packets in the message
        $packet_no = $this->unpackBinary($msg, "n", 0, 2);
        $packets = array();
        $j = 2;
        foreach(range(0, $packet_no-1) as $i){
            $packet_len = $this->unpackBinary($msg, "n", $j, $j+2);
            $packets[] = array("tick_data" => substr($msg, $j+2, $j+2+$packet_len), "tick_len" => $packet_len);
            $j = $j + 2 + $packet_len;
        }
        return $packets;
    }

    // Convert unix timestamp to local timestamp
    function convTime(int $unix_time){
        $dt = new DateTime();
        $dt->setTimestamp($unix_time);
        // Set local time zone
        $dt->setTimezone(new DateTimeZone("Asia/Kolkata"));
        $local_time = $dt->format('Y-m-d H:i:s');
        return $local_time;
    }

    // Parse binary data to a ticks 
    function parseBinary(String $msg)
    {
        $packets = $this->splitPackets($msg);
        $data = [];
        foreach($packets as $packet){
            $instrument_token = $this->unpackBinary($packet['tick_data'], "N", 0, 4);
            $segment = $instrument_token & 0xff;
        
            // Add price divisor based on segment
            if ($segment == $this->EXCHANGE_MAP["cds"]){
                $divisor = 10000000.0;
            } elseif ($segment == $this->EXCHANGE_MAP["bcd"]){
                $divisor = 10000.0;
            } else {
                $divisor = 100.0;
            }

            // Make all indices as non-tradeable
            if ($segment == $this->EXCHANGE_MAP["indices"]){
                $tradable = false;
            } else {
                $tradable = true;
            }

            // LTP packets
            if($packet['tick_len'] == 8){
                $tick = array("tradable" => $tradable, "mode" => $this->MODE_LTP,
                            "instrument_token" => $instrument_token,
                            "last_price" => $this->unpackBinary($packet['tick_data'], "N", 4, 8)/$divisor
                        );
            }

            // Indices quote and full mode
            elseif($packet['tick_len'] == 28 || $packet['tick_len'] == 32){
                // Set indices mode
                if($packet['tick_len'] == 28){
                    $mode = $this->MODE_QUOTE;
                } else{
                    $mode = $this->MODE_FULL;
                }
                $last_price = $this->unpackBinary($packet['tick_data'], "N", 4, 8)/$divisor;
                $close_price = $this->unpackBinary($packet['tick_data'], "N", 20, 24)/$divisor;
                $tick = array("tradable" => $tradable, "mode" => $mode,
                            "instrument_token" => $instrument_token,
                            "last_price" => $last_price,
                            "ohlc" => array(
                                "high" => $this->unpackBinary($packet['tick_data'], "N", 8, 12)/$divisor,
                                "low" => $this->unpackBinary($packet['tick_data'], "N", 12, 16)/$divisor,
                                "open" => $this->unpackBinary($packet['tick_data'], "N", 16, 20)/$divisor,
                                "close" => $close_price
                            ),
                            "price_change" => $last_price - $close_price
                        );
                // Add Exchange timestamp for full mode indices
                if($packet['tick_len'] == 32){
                    $exchange_timestamp = $this->unpackBinary($packet['tick_data'], "N", 28, 32);
                    $tick['exchange_timestamp'] = $this->convTime($exchange_timestamp);
                }
            }

            // Quote and full mode
            elseif($packet['tick_len'] == 44 || $packet['tick_len'] == 184){
                if($packet['tick_len'] == 44){
                    $mode = $this->MODE_QUOTE;
                } else{
                    $mode = $this->MODE_FULL;
                }
                $tick = array("tradable" => $tradable, "mode" => $mode,
                            "instrument_token" => $instrument_token,
                            "last_price" => $this->unpackBinary($packet['tick_data'], "N", 4, 8)/$divisor,
                            "last_traded_quantity" => $this->unpackBinary($packet['tick_data'], "N", 8, 12),
                            "average_traded_price" => $this->unpackBinary($packet['tick_data'], "N", 12, 16)/$divisor,
                            "volume_traded" => $this->unpackBinary($packet['tick_data'], "N", 16, 20),
                            "total_buy_quantity" => $this->unpackBinary($packet['tick_data'], "N", 20, 24),
                            "total_sell_quantity" => $this->unpackBinary($packet['tick_data'], "N", 24, 28),
                            "ohlc" => array(
                                "open" => $this->unpackBinary($packet['tick_data'], "N", 28, 32)/$divisor,
                                "high" => $this->unpackBinary($packet['tick_data'], "N", 32, 36)/$divisor,
                                "low" => $this->unpackBinary($packet['tick_data'], "N", 36, 40)/$divisor,
                                "close" => $this->unpackBinary($packet['tick_data'], "N", 40, 44)/$divisor
                            ));
                
                // Parse full mode
                if($packet['tick_len'] == 184){
                    $last_trade_timestamp = $this->unpackBinary($packet['tick_data'], "N", 44, 48);
                    $tick['last_trade_time'] = $this->convTime($last_trade_timestamp);
                    $tick['oi'] = $this->unpackBinary($packet['tick_data'], "N", 48, 52);
                    $tick['oi_day_high'] = $this->unpackBinary($packet['tick_data'], "N", 52, 56);
                    $tick['oi_day_low'] = $this->unpackBinary($packet['tick_data'], "N", 56, 60);
                    $exchange_timestamp = $this->unpackBinary($packet['tick_data'], "N", 60, 64);
                    $tick['exchange_timestamp'] = $this->convTime($exchange_timestamp);

                    // Market depth entries
                    $depth = array("buy"=> [], "sell"=> []);
                    $buy_loc = 64;
                    $sell_loc = 124;
                    $depth_len = ($sell_loc-$buy_loc)/12;
                    foreach(range(0, $depth_len-1) as $i){
                        // Buy market depth
                        $tick['depth']['buy'][$i] = array(
                            'quantity' => $this->unpackBinary($packet['tick_data'], "N", $buy_loc , $buy_loc+4),
                            'price' => $this->unpackBinary($packet['tick_data'], "N", $buy_loc+4 , $buy_loc+8)/$divisor,
                            'orders' => $this->unpackBinary($packet['tick_data'], "n", $buy_loc+8 , $buy_loc+12)
                        );

                        // Sell market depth
                        $tick['depth']['sell'][$i] = array(
                            'quantity' => $this->unpackBinary($packet['tick_data'], "N", $sell_loc , $sell_loc+4),
                            'price' => $this->unpackBinary($packet['tick_data'], "N", $sell_loc+4 , $sell_loc+8)/$divisor,
                            'orders' => $this->unpackBinary($packet['tick_data'], "n", $sell_loc+8 , $sell_loc+12)
                        );
                        $buy_loc += 12;
                        $sell_loc += 12;
                    }
                }
            }
            return $tick;
        }
    }
}
