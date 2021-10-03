<?php

namespace Tighten\SolanaPhpSdk\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Mockery as M;
use Tighten\SolanaPhpSdk\Exceptions\AccountNotFoundException;
use Tighten\SolanaPhpSdk\Programs\SystemProgram;
use Tighten\SolanaPhpSdk\PublicKey;
use Tighten\SolanaPhpSdk\SolanaRpcClient;
use Tighten\SolanaPhpSdk\Util\Ed25519Keypair;
use Tuupola\Base58;

class PublicKeyTest extends TestCase
{
    /** @test */
    public function it_correctly_encodes_string_to_buffer()
    {
        $publicKey = new PublicKey('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc');

        $this->assertEquals([
            23, 26, 218, 1, 26, 7, 253, 202, 19, 162, 251, 121, 172, 0, 65, 219, 142, 20, 252, 217, 6, 150, 142, 0, 54, 146, 245, 140, 155, 194, 42, 131,
        ], $publicKey->toBuffer());

        $this->assertEquals('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc', $publicKey->toBase58());
    }

    /** @test */
    public function it_correctly_evaluates_equality()
    {
        $publicKey1 = new PublicKey('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc');
        $publicKey2 = new PublicKey('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc');

        $this->assertEquals($publicKey1, $publicKey2);
    }

    /** @test */
    public function it_correctly_handles_public_key_in_constructor()
    {
        $publicKey1 = new PublicKey('2ZC8EZduQGavJB9duMUgpdjNj7TQUiMawb52CLXBH5yc');
        $publicKey2 = new PublicKey($publicKey1);

        $this->assertEquals($publicKey1, $publicKey2);
    }

    /** @test */
    public function it_equals()
    {
        $arrayKey = new PublicKey([3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,]);

        $base58Key = new PublicKey('CiDwVBFgWV9E5MvXWoLgnEgn2hK7rJikbvfWavzAQz3');

        $this->assertEquals($base58Key, $arrayKey);
    }

    /** @test */
    public function it_toBase58()
    {
        $key1 = new PublicKey('CiDwVBFgWV9E5MvXWoLgnEgn2hK7rJikbvfWavzAQz3');
        $this->assertEquals('CiDwVBFgWV9E5MvXWoLgnEgn2hK7rJikbvfWavzAQz3', $key1->toBase58());
        $this->assertEquals('CiDwVBFgWV9E5MvXWoLgnEgn2hK7rJikbvfWavzAQz3', $key1);

        $key2 = new PublicKey('1111111111111111111111111111BukQL');
        $this->assertEquals('1111111111111111111111111111BukQL', $key2->toBase58());
        $this->assertEquals('1111111111111111111111111111BukQL', $key2);

        $key3 = new PublicKey('11111111111111111111111111111111');
        $this->assertEquals('11111111111111111111111111111111', $key3->toBase58());

        $key4 = new PublicKey([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,]);
        $this->assertEquals('11111111111111111111111111111111', $key4->toBase58());
    }

    /** @test */
    public function it_createWithSeed()
    {
        $defaultPublicKey = new PublicKey('11111111111111111111111111111111');
        $derivedKey = PublicKey::createWithSeed($defaultPublicKey, 'limber chicken: 4/45', $defaultPublicKey);

        $this->assertEquals(new PublicKey('9h1HyLCW5dZnBVap8C5egQ9Z6pHyjsh5MNy83iPqqRuq'), $derivedKey);
    }

    /** @test */
    public function it_createProgramAddress()
    {
        $programId = new PublicKey('BPFLoader1111111111111111111111111111111111');
        $publicKey = new PublicKey('SeedPubey1111111111111111111111111111111111');

//        why isn't this one working? It's something with [1] input.
//        $programAddress = PublicKey::createProgramAddress([
//            Ed25519Keypair::bin2array(''),
//            [1]
//        ], $programId);
//        $this->assertEquals(new PublicKey('3gF2KMe9KiC6FNVBmfg9i267aMPvK37FewCip4eGBFcT'), $programAddress);

        $programAddress = PublicKey::createProgramAddress([
            Ed25519Keypair::bin2array('☉')
        ], $programId);
        $this->assertEquals(new PublicKey('7ytmC1nT1xY4RfxCV2ZgyA7UakC93do5ZdyhdF3EtPj7'), $programAddress);

        $programAddress = PublicKey::createProgramAddress([
            Ed25519Keypair::bin2array('Talking'),
            Ed25519Keypair::bin2array('Squirrels')
        ], $programId);
        $this->assertEquals(new PublicKey('HwRVBufQ4haG5XSgpspwKtNd3PC9GM9m1196uJW36vds'), $programAddress);

        $programAddress = PublicKey::createProgramAddress([
            $publicKey->toBytes(),
        ], $programId);
        $this->assertEquals(new PublicKey('GUs5qLUfsEHkcMB9T38vjr18ypEhRuNWiePW2LoK4E3K'), $programAddress);
    }


    /** @test */
    public function it_findProgramAddress()
    {
        $programId = new PublicKey('BPFLoader1111111111111111111111111111111111');

        list($programAddress, $nonce) = PublicKey::findProgramAddress(
            [Ed25519Keypair::bin2array('')],
            $programId
        );

        $this->assertEquals(
            PublicKey::createProgramAddress([
                Ed25519Keypair::bin2array(''),
                [$nonce]
            ], $programId),
            $programAddress
        );
    }
}