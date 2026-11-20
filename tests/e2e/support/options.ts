import { wpCli } from './wp-cli';

export type Options = {
	get: ( name: string ) => string;
	has: ( name: string ) => boolean;
	set: ( name: string, value: string ) => void;
};

const b64 = ( value: string ): string => Buffer.from( value ).toString( 'base64' );

// Every WP-CLI call costs about two seconds, so first-time set() snapshots and
// updates in one `wp eval`, and restore() puts every changed option back in one.
// State is [ existed, value ], serialized so absent and false-valued options stay distinct.
function snapshotAndUpdate( name: string, value: string ): string {
	return wpCli( 'eval', `global $wpdb; $name = base64_decode( '${ b64( name ) }' ); $exists = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $name ) ); echo base64_encode( serialize( [ $exists, get_option( $name ) ] ) ); update_option( $name, base64_decode( '${ b64( value ) }' ) );` );
}

function restoreAll( original: Map<string, string> ): void {
	if ( original.size === 0 ) {
		return;
	}
	const payload = b64( JSON.stringify( [ ...original ].map( ( [ name, state ] ) => [ b64( name ), state ] ) ) );
	wpCli( 'eval', `foreach ( json_decode( base64_decode( '${ payload }' ), true ) as [ $encoded_name, $encoded_state ] ) { $name = base64_decode( $encoded_name ); $state = unserialize( base64_decode( $encoded_state ), [ 'allowed_classes' => true ] ); if ( $state[0] ) { update_option( $name, $state[1] ); } else { delete_option( $name ); } }` );
}

export async function withOptions<T>( run: ( options: Options ) => Promise<T> ): Promise<T> {
	const original = new Map<string, string>();
	const options: Options = {
		get: ( name ) => wpCli( 'option', 'get', name ),
		has: ( name ) => wpCli( 'eval', `global $wpdb; $name = base64_decode( '${ b64( name ) }' ); echo $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $name ) ) ? '1' : '0';` ) === '1',
		set: ( name, value ) => {
			if ( original.has( name ) ) {
				wpCli( 'option', 'update', name, value );
			} else {
				original.set( name, snapshotAndUpdate( name, value ) );
			}
		},
	};
	try {
		return await run( options );
	} finally {
		restoreAll( original );
	}
}
