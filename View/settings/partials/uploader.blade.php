	<div class="form-group{{ $errors->has('editor') ? ' has-error' : '' }}">
	    {!! Form::label('editor', 'Input label') !!}
	    {!! Form::textarea('editor', null, ['class' => 'form-control', 'required' => 'required']) !!}
	    <small class="text-danger">{{ $errors->first('editor') }}</small>
	</div>