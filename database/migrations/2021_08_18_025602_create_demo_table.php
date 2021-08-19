<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDemoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('demo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
//            $table->bigIncrements('id_bigIncrements');
            $table->bigInteger('votes_bigInteger');
            $table->binary('data');
            $table->boolean('confirmed');
            $table->char('name_char', 100);
//            $table->date('created_at');
//            $table->dateTime('created_at', 0);
//            $table->dateTimeTz('created_at', 0);
            $table->decimal('amount_decimal', 8, 2);
            $table->double('amount_double', 8, 2);
            $table->enum('level', ['easy', 'hard']);
            $table->float('amount_float', 8, 2);
            $table->geometry('positions_geometry');
            $table->geometryCollection('positions_geometrycollection');
//            $table->increments('id_increments');
            $table->integer('votes_integer');
            $table->ipAddress('visitor_ipaddress');
            $table->json('options_json');
            $table->jsonb('options_jsonb');
            $table->lineString('positions_lineString');
            $table->longText('description_longText');
            $table->macAddress('device_macAddress');
//            $table->mediumIncrements('id_mediumIncrements');
            $table->mediumInteger('votes_mediumInteger');
            $table->mediumText('description_mediumText');
            $table->morphs('taggable_morphs');
            $table->uuidMorphs('taggable_uuidMorphs');
            $table->multiLineString('positions_multiLineString');
            $table->multiPoint('positions_multiPoint');
            $table->multiPolygon('positions_multiPolygon');
            $table->nullableMorphs('taggable');
//            $table->nullableUuidMorphs('taggable_nullableUuidMorphs');
//            $table->nullableTimestamps(0);
            $table->point('position_point');
            $table->polygon('positions_polygon');
            $table->rememberToken();
            $table->set('flavors_set', ['strawberry', 'vanilla']);
//            $table->smallIncrements('id_smallIncrements');
            $table->smallInteger('votes_smallInteger');
            $table->softDeletes('deleted_at_softDeletes', 0);
            $table->softDeletesTz('deleted_at_softDeletesTz', 0);
            $table->string('name_string_string', 100);
            $table->text('description_text');
            $table->time('sunrise_time', 0);
            $table->timeTz('sunrise_timeTz', 0);
            $table->timestamp('added_on_timestamp', 0);
            $table->timestampTz('added_on_timestampTz', 0);
//            $table->timestamps(0);
            $table->timestampsTz(0);
//            $table->tinyIncrements('id_tinyIncrements');
            $table->tinyInteger('votes_tinyInteger');
            $table->unsignedBigInteger('votes_unsignedBigInteger');
            $table->unsignedDecimal('amount_unsignedDecimal', 8, 2);
            $table->unsignedInteger('votes_unsignedInteger');
            $table->unsignedMediumInteger('votes_unsignedMediumInteger');
            $table->unsignedSmallInteger('votes_unsignedSmallInteger');
            $table->unsignedTinyInteger('votes_unsignedTinyInteger');
            $table->uuid('id_uuid');
            $table->year('birth_year_year');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('demo', function (Blueprint $table) {
            //
        });
    }
}
