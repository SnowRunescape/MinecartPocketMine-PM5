<?php

namespace Minecart\task;

use pocketmine\console\ConsoleCommandSender;
use pocketmine\scheduler\AsyncTask;

use pocketmine\lang\Language;

use Minecart\utils\Form;
use Minecart\utils\API;
use Minecart\Minecart;
use Minecart\utils\Errors;
use Minecart\utils\Messages;

class RedeemCashAsync extends AsyncTask
{
    private $username;
    private $authorization;
    private $shopServer;

    public function __construct(string $username, string $authorization, string $shopServer)
    {
        $this->username = $username;
        $this->authorization = $authorization;
        $this->shopServer = $shopServer;
    }

    public function onRun() : void
    {
        $api = new API();
        $api->setAuthorization($this->authorization);
        $api->setShopServer($this->shopServer);
        $api->setParams(["username" => $this->username]);
        $api->setURL(API::REDEEMCASH_URI);

        $this->setResult($api->send());
    }

    public function onCompletion() : void
    {
        $player = Minecart::getInstance()->getServer()->getPlayerExact($this->username);
        $response = $this->getResult();

        if (!empty($response)) {
            $statusCode = $response["statusCode"];
            if ($statusCode == 200) {
                $response = $response["response"];

                $command = $this->parseText($response["command"], $player, $response);

                if (Minecart::getInstance()->getServer()->dispatchCommand(new ConsoleCommandSender(Minecart::getInstance()->getServer(), new Language("eng")), $command)) {
                    $messages = new Messages();
                    $messages->sendGlobalInfo($player, "cash", $response["cash"]);
                } else {
                    $error = Minecart::getInstance()->getMessage("error.redeem-cash");
                    $error = str_replace("{cash}", $response["cash"], $error);

                    $player->sendMessage($error);
                }
            } else {
                $form = new Form();
                $form->setTitle("Erro!");

                $errors = new Errors();
                $error = $errors->getError($player, $response["response"]["code"] ?? $statusCode, true);

                $form->setMessage($error);
                $form->showFormError($player);
            }
        } else {
            $player->sendMessage(Minecart::getInstance()->getMessage("error.internal-error"));
        }
    }

    private function parseText(string $text, Player $player, array $response) : string
    {
        return str_replace(["{player.name}", "{cash.quantity}"], [$player->getName(), $response["cash"]], $text);
    }
}
