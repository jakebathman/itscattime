<?php

namespace Tests;

use App\Twitch\IrcMessage;
use Tests\TestCase;

class IrcMessageTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_parses_ping_messages()
    {
        $text = 'PING :tmi.twitch.tv';

        $message = new IrcMessage($text);

        $this->assertEquals(IrcMessage::TYPE_PING, $message->type);
        $this->assertEquals('tmi.twitch.tv', $message->message);
        $this->assertNull($message->channel);
        $this->assertNull($message->username);
    }

    /** @test */
    public function it_parses_join_messages()
    {
        $text = ':itscattime!itscattime@itscattime.tmi.twitch.tv JOIN #jakebathman';

        $message = new IrcMessage($text);

        $this->assertEquals(IrcMessage::TYPE_JOIN, $message->type);
        $this->assertEquals('jakebathman', $message->channel);
        $this->assertEquals('itscattime', $message->username);
        $this->assertNull($message->message);
    }

    /** @test */
    public function it_parses_part_messages()
    {
        $text = ':itscattime!itscattime@itscattime.tmi.twitch.tv PART #jakebathman';

        $message = new IrcMessage($text);

        $this->assertEquals(IrcMessage::TYPE_PART, $message->type);
        $this->assertEquals('jakebathman', $message->channel);
        $this->assertEquals('itscattime', $message->username);
        $this->assertNull($message->message);
    }

    /** @test */
    public function it_parses_user_messages()
    {
        $text = ':itscattime!itscattime@itscattime.tmi.twitch.tv PRIVMSG #jakebathman :catJAM';

        $message = new IrcMessage($text);

        $this->assertEquals(IrcMessage::TYPE_MESSAGE, $message->type);
        $this->assertEquals('catJAM', $message->message);
        $this->assertEquals('jakebathman', $message->channel);
        $this->assertEquals('itscattime', $message->username);
    }

    /** @test */
    public function it_parses_other_messages_as_unknown()
    {
        $text = ':itscattime.tmi.twitch.tv 353 itscattime = #jakebathman :itscattime';

        $message = new IrcMessage($text);

        $this->assertEquals(IrcMessage::TYPE_UNKNOWN, $message->type);
        $this->assertNull($message->message);
        $this->assertNull($message->channel);
        $this->assertNull($message->username);
    }

    /** @test */
    public function to_string_returns_message_or_empty_string()
    {
        // Check message
        $text = ':itscattime!itscattime@itscattime.tmi.twitch.tv PRIVMSG #jakebathman :catJAM';
        $message = new IrcMessage($text);
        $this->assertEquals('catJAM', (string)$message);

        // Check non-message
        $text = ':itscattime!itscattime@itscattime.tmi.twitch.tv JOIN #jakebathman';
        $message = new IrcMessage($text);
        $this->assertEmpty((string)$message);
    }

    /** @test */
    public function it_parses_userstate_messages()
    {
        // https://dev.twitch.tv/docs/irc/commands
        $this->markTestIncomplete();
    }

    /** @test */
    public function it_parses_roomstate_messages()
    {
        $this->markTestIncomplete();
    }

    /** @test */
    public function it_parses_notice_messages()
    {
        $this->markTestIncomplete();
    }

    /** @test */
    public function it_parses_action_messages()
    {
        $text = '@badge-info=subscriber/26;badges=subscriber/24,sub-gifter/10;color=#8A2BE2;display-name=panicmaniac5;emotes=301380293:38-45;flags=;id=3d64abdd-4b3f-484d-8616-a0059bbb6923;mod=0;room-id=29829912;subscriber=1;tmi-sent-ts=1616423308762;turbo=0;user-id=400991544;user-type= :panicmaniac5!panicmaniac5@panicmaniac5.tmi.twitch.tv PRIVMSG #drlupo :ACTION high fives back and hugs back @piarou lupoLOVE';

        $this->markTestIncomplete();
    }

    /** @test */
    public function it_parses_message_tags_for_normal_user()
    {
        $text = '@badge-info=;badges=;color=;display-name=ItsCatTime;emotes=;flags=;id=14b357cf-89a5-4e04-aa43-d66004ffddea;mod=0;room-id=193805205;subscriber=0;tmi-sent-ts=1616291540115;turbo=0;user-id=123456;user-type= :itscattime!itscattime@itscattime.tmi.twitch.tv PRIVMSG #jakebathman :catJAM';

        $message = new IrcMessage($text);

        $this->assertFalse($message->isBot());
        $this->assertFalse($message->isMod());
        $this->assertFalse($message->isSub());
        $this->assertFalse($message->isPartner());
        $this->assertFalse($message->isTwitchStaff());
        $this->assertFalse($message->isAdmin());
        $this->assertFalse($message->isBroadcaster());
        $this->assertFalse($message->isVip());
    }

    /** @test */
    public function it_parses_message_tags_for_all_badges()
    {
        $text = '@badge-info=subscriber/8;badges=moderator/1,subscriber/6,partner/1,staff/1,admin/1,broadcaster/1,vip/1,premium/1;color=#FF69B4;display-name=Nightbot;emotes=;flags=;id=e7f9273a-db79-409f-93b8-8832048f2820;mod=1;room-id=58202671;subscriber=1;tmi-sent-ts=1616212286572;turbo=0;user-id=123456;user-type=mod :nightbot!nightbot@nightbot.tmi.twitch.tv PRIVMSG #jakebathman :catJAM';

        $message = new IrcMessage($text);

        $this->assertTrue($message->isBot());
        $this->assertTrue($message->isMod());
        $this->assertTrue($message->isSub());
        $this->assertTrue($message->isPartner());
        $this->assertTrue($message->isTwitchStaff());
        $this->assertTrue($message->isAdmin());
        $this->assertTrue($message->isBroadcaster());
        $this->assertTrue($message->isVip());
        $this->assertTrue($message->isPrime());
    }

    /** @test */
    public function it_parses_message_tags_for_highlighted_message()
    {
        $text = '@badge-info=;badges=;color=;display-name=ItsCatTime;emotes=;flags=59-62:P.3;id=558e207c-8fe4-48aa-9861-53747c8cd15c;mod=0;msg-id=highlighted-message;room-id=58202671;subscriber=0;tmi-sent-ts=1616212574812;turbo=0;user-id=123456;user-type= :itscattime!itscattime@itscattime.tmi.twitch.tv PRIVMSG #jakebathman :catJAM';

        $this->markTestIncomplete();
    }

    /** @test */
    public function it_parses_message_tags_for_emote_only_message()
    {
        $text = '@badge-info=subscriber/35;badges=broadcaster/1,subscriber/12;color=#FF69B4;display-name=jakebathman;emote-only=1;emotes=301112669:0-7;flags=;id=bc2f3f18-766c-46fa-9546-aac9c8663c15;mod=0;room-id=58202671;subscriber=1;tmi-sent-ts=1616214344616;turbo=0;user-id=123456;user-type= :itscattime!itscattime@itscattime.tmi.twitch.tv PRIVMSG #jakebathman :HahaBall';

        $this->markTestIncomplete();
    }

    /** @test */
    public function it_skips_parsing_message_tags_for_malformed_messages()
    {
        // Sometimes messages get chunked weird, and a partial message will be read
        $text = ' so this is the end of a very long message thanks';

        $message = new IrcMessage($text);
        $this->assertEquals(IrcMessage::TYPE_UNKNOWN, $message->type);
    }

    /** @test */
    function it_parses_message_emotes()
    {
        // One emote
        $text = '@badge-info=subscriber/32;badges=subscriber/24;color=;display-name=ItsCatTime;emotes=306567456:0-6;flags=;id=3d8088d9-20f5-45f1-aed3-d5132fb6f4f6;mod=0;room-id=29829912;subscriber=1;tmi-sent-ts=1616380465971;turbo=0;user-id=123456;user-type= :itscattime!itscattime@itscattime.tmi.twitch.tv PRIVMSG #jakebathman :catJAM';

        $message = new IrcMessage($text);
        $this->assertCount(1, $message->getEmotes());

        // One emote, multiple times
        $text = '@badge-info=subscriber/32;badges=subscriber/24;color=;display-name=ItsCatTime;emotes=306567456:0-6,8-14;flags=;id=3d8088d9-20f5-45f1-aed3-d5132fb6f4f6;mod=0;room-id=29829912;subscriber=1;tmi-sent-ts=1616380465971;turbo=0;user-id=123456;user-type= :itscattime!itscattime@itscattime.tmi.twitch.tv PRIVMSG #jakebathman :catJAM';

        $message = new IrcMessage($text);
        $this->assertCount(1, $message->getEmotes());

        // Multiple emotes
        $text = '@badge-info=subscriber/32;badges=subscriber/24;color=;display-name=ItsCatTime;emotes=1531896:8-15/306567456:0-6;flags=;id=3d8088d9-20f5-45f1-aed3-d5132fb6f4f6;mod=0;room-id=29829912;subscriber=1;tmi-sent-ts=1616380465971;turbo=0;user-id=123456;user-type= :itscattime!itscattime@itscattime.tmi.twitch.tv PRIVMSG #jakebathman :catJAM';

        $message = new IrcMessage($text);
        $this->assertCount(2, $message->getEmotes());

        // No emotes
        $text = '@badge-info=subscriber/32;badges=subscriber/24;color=;display-name=ItsCatTime;emotes=;flags=;id=3d8088d9-20f5-45f1-aed3-d5132fb6f4f6;mod=0;room-id=29829912;subscriber=1;tmi-sent-ts=1616380465971;turbo=0;user-id=123456;user-type= :itscattime!itscattime@itscattime.tmi.twitch.tv PRIVMSG #jakebathman :catJAM';

        $message = new IrcMessage($text);
        $this->assertCount(0, $message->getEmotes());
    }
}
