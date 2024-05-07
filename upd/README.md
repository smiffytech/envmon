# UPD - The Universal Payload Decoder

When you have to be mindful of power, sending messages over the air, you want to keep them **small**. Whilst sending a JSON document is dead easy, it's an awful lot of bytes, when the payload doesn't need to be human-readable.

All the LoRaWAN devices I have dealt with to date send data as a minimised bitstream. First four bytes might be the serial number, next two a temperature measurement, then another two for humidity. Sixteen bytes, just sixteen characters.

So when they get where they are going, we need to change this into something we can work with, and that's where payload decoders come in. Networks TTN and Helium allow user-defined payload decoders, written in JavaScript (don't know about other languages.) Drei ThingPark (Austria), you have to work with pre-defined ones. Manufacturers will generally provide the code - but it doesn't always work, and sometimes the converted data formats can be less than ideal.

I have had to fix payload decoders where the manufacturers had forgotten that temperatures can go negative, and that SIGNED numbers exist. When a little-endian 16 bit float is above zero, you can convert it as an unsigned number. But when the temperature drops, the numbers coming back suddenly go right UP.

Adding this to the fact that I do NOT like the idea of running arbitrary code, no matter how well sandboxed, I have come to the conclusion that this is approaching the problem from completely the wrong direction. What we should be giving the networks is not a decoder, but a DESCRIPTION of the data, and how to decode it.

But, if dealing with multiple networks, do I really want to be putting even descriptions on multiple dashboards? And what about those where you have to choose from a list?

So my preference is to receive the raw data, and deal with it myself - with a Universal Payload Decoder, a simple Python module that can read a control document, and use that to convert the payload, either into JSON, or just return a dictionary for further processing.

## UPD Description Document

### Sections

* `meta` - not actually necessary, although may be useful documentation. Arbitrary keys.
* `general` - applies to both input and output messages as a whole.
  * `structure` - output document is either **flat**, a set of key-value pairs (KVPs), or **hierarchy**, where each field has a record, with a **value** and arbitrary descriptive KVPs.
  * `input_format` - at the moment, based on what I have seen, either Base64 or hexadecimal encoding.
* `fields` - the actual measurements, or other values. Associative array. This is under active development, as I try to consolidate the different way in which the raw data is formatted.
  * `match` 
    * `position` - this field starts at byte position **x** of the message
      * this requires `start_byte`
    * `channel` - use channel/channel type to identify the field. I am assuming that this is due to the byte order of certain devices to be inconsistent, possibly due to version modifications, device variants (so shit design practice.) So the message is in chunks, without a fixed start byte, but of fixed length. This corresponds to the (also bad) practice of using an array in  a JSON document, iterating through, and waiting to see a certain item, rather than using an associative array with keys.
      * this requires
        * `channel_id` - numeric identifier
        * `channel_id_byte` - zero indexed, position in message chunk
        * `channel_type` - numerical identifier, might be `null`
        * `channel_type_byte` - zero indexed, position in message chunk, because we don't know if Milesight's example (below) is consistent.   
  * `data_length` - irrespective of the `match` type, the number of bytes representing this data. So an 8, 16, 32 bit value will be 1, 2, 4 length.
  * `start_byte` - can be `null`, or omitted, if `match` is not `position`.
  * `type` - how are we converting this? Is it:
    * `int`
    * `float`
    * `string`
  * `signed` - for `int`, `float`
  * `endian` - for `int`, `float`.  Values are `l` (little) and `b` (big)`l` is lower case L.
  * `multiplier` - what do we do with the converted number? When we are describing something like this, we need to rationalise, and not have an if/or multiply/divide, add/subtract. If this is a division? This value is 1 / divisor. 

```
{
	"meta": {
		"name": "em300-th",
		"description": "temperature, hummidity sensor"
		"manufacturer": "Milesight"
	},
	"general": {
		"structure": "flat",
		"input_format": "base64"
	}
	"fields": {
		"temperature": {
			"match": "channel",
			"channel_id": "0x0d",
			"channel_id_byte": 0,
			"channel_type": "0xc7",
			"channel_type_byte": 1,
			"data_length": 2,
			"start_byte": null,
			"type": "int",
			"signed": false,
			"endian": "l",
			"multiplier": 0.1
		},
		"humidity": {
		
		},
		"battery": {
		
		}
	}
}
```

