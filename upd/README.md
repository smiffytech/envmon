# UPD - The Universal Payload Decoder

When you have to be mindful of power, sending messages over the air, you want to keep them **small**. Whilst sending a JSON document is dead easy, it's an awful lot of bytes, when the payload doesn't need to be human-readable.

All the LoRaWAN devices I have dealt with to date send data as a minimised bitstream. First four bytes might be the serial number, next two a temperature measurement, then another two for humidity. Sixteen bytes, just sixteen characters.

So when they get where they are going, we need to change this into something we can work with, and that's where payload decoders come in. Networks TTN and Helium allow user-defined payload decoders, written in JavaScript (don't know about other languages.) Drei ThingPark (Austria), you have to work with pre-defined ones. Manufacturers will generally provide the code - but it doesn't always work, and sometimes the converted data formats can be less than ideal.

I have had to fix payload decoders where the manufacturers had forgotten that temperatures can go negative, and that SIGNED numbers exist. When a little-endian 16 bit float is above zero, you can convert it as an unsigned number. But when the temperature drops, the numbers coming back suddenly go right UP.

Adding this to the fact that I do NOT like the idea of running arbitrary code, no matter how well sandboxed, I have come to the conclusion that this is approaching the problem from completely the wrong direction. What we should be giving the networks is not a decoder, but a DESCRIPTION of the data, and how to decode it.

But, if dealing with multiple networks, do I really want to be putting even descriptions on multiple dashboards? And what about those where you have to choose from a list?

So my preference is to receive the raw data, and deal with it myself - with a Universal Payload Decoder, a simple Python module that can read a control document, and use that to convert the payload, either into JSON, or just return a dictionary for further processing.

## UPD Description Document

```
{
	"meta": {
		"name": "em300-udl",
		"manufacturer": "Milesight"
	},
	"general": {
		"structure": "flat",
		"input_format": "base64"
	}
	"fields": {
	
	}
}
```

