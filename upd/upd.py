"""
Universal Payload Decoder

https://github.com/smiffytech/envmon/tree/master/upd

Created 2024-05-08
"""
import base64
import json
import os

class UPD:
    
    def __init__(self, mode='json', **kwargs):
        if mode == 'json':
            self.mode = 'json'
        elif mode == 'dict':
            self.mode = 'dict'
        elif mode == 'file':
            self.mode = 'file'
        else:
            raise ValueError('Mode should be json (default) dict, or file.')
        
    def decode(self, control, data):
        if self.mode == 'dict' and type(control) is not dict:
            raise TypeError('mode = dict requires control to be a dictionary.')
        elif self.mode != 'dict' and type(control) is not str:
            raise TypeError('mode = %s requires control to be a string (JSON or file name).' % self.mode)
        
        if self.mode == 'file':
            if not os.path.isfile(control):
                raise Exception('Control file %s not found.' % control)
            
            with open(control) as fh:
                try:
                    c = json.load(fh)
                except Exception as e:
                    raise Exception('Could not parse json from file %s: %s' % (control, str(e)))

        elif self.mode == 'json':
            try:
                c = json.loads(control)
            except Exception as e:
                raise Exception('Could no parse JSON control structure: %s' % str(e))
        else:
            c = control

        if len(data) == 0:
            raise Warning('Data is zero sized.')

        if 'general' not in c \
            or 'input_format' not in c['general'] \
            or 'output_format' not in c['general'] \
            or 'fields' not in c:
                raise Warning('Invalid control structure')
        
        if c['general']['input_format'] not in ('base64', 'hex', 'bytes'):
            raise Warning('Invalid input_format %s Must be base64, bytes, or hex.' 
                % c['general']['input_format'])
        
        if c['general']['output_format'] not in ('dict', 'json'):
            raise Warning('Invalid output_format %s Must be dict, or json.' 
                % c['general']['output_format'])
        
        if c['general']['input_format'] == 'base64':
            try:
                b = bytearray(base64.b64decode(data))
            except Exception as e:
                raise Warning('Could not decode Base64 data: %s' % str(e))

        elif c['general']['input_format'] == 'hex':
            try:
                # Spaces are OK, 0x and : aren't.
                b = bytearray.fromhex(data.replace(':', '').lstrip('0x'))
            except Exception as e:
                raise Warning('Could not decode hex data: %s' % str(e))

        else:
            try:
                b = bytearray(data)
            except Exception as e:
                raise Warning('Could not read data as bytes.')

        self.datasize = len(b)

        out = {}

        for f in c['fields']:
            pass


        


