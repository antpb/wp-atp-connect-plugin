import { useState, useEffect } from "@wordpress/element";

function LoginForm(props) {
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [postTypes, setPostTypes] = useState('');
    const [host, setHost] = useState('');
	const [saved, setSaved] = useState(false);

	useEffect(() => {
		console.log('settings:', props.settings)
		if(!props.settings?.data?.unified_session_data) return;
		setUsername(props.settings.data.unified_session_data['username']);
		setPassword(props.settings.data.unified_session_data['password']);
		setHost(props.settings.data.unified_session_data['host']);
		setPostTypes(props.settings.data.unified_session_data['postTypes']);
	}, [settings]);

	const handleSubmit = async (e) => {
		e.preventDefault();
		try {
			console.log('Attempting login with username:', username);
			const response = await fetch('/wp-json/atp_connect/v1/bluesky_login/', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': wpApiSettings.nonce,
				},
				body: JSON.stringify({ username, password, host, postTypes }),
			});
	
			if (!response.ok) {
				throw new Error('Login failed');
			}
	
			const data = await response.json();

			setSaved(true);
			setTimeout(() => {
				setSaved(false);
			}, 3000);
			
			console.log('Login successful. Response:', data);
		} catch (error) {
			console.error('Login error:', error);
		}
	};

	// default styles
	const styles = {
		form: {
			display: 'flex',
			flexDirection: 'column',
			alignItems: 'left',
			justifyContent: 'left',
		},
		label: {
			display: 'flex',
			flexDirection: 'column',
			marginBottom: '1rem',
			paddingBottom: '10px',
		},
		labelSaved: {
			display: 'flex',
			flexDirection: 'column',
			marginBottom: '1rem',
			paddingBottom: '10px',
			color: 'green',
			fontWeight: 'bold',
		},
		input: {
			padding: '0.5rem',
			border: '1px solid #ddd',
			borderRadius: '4px',
			fontSize: '1rem',
			maxWidth: '300px',
		},
		inputPass: {
			padding: '0.5rem',
			border: '1px solid #ddd',
			borderRadius: '4px',
			fontSize: '1rem',
			maxWidth: '100px',
		},
		button: {
			padding: '0.5rem',
			border: '1px solid #21759b',
			backgroundColor: '#21759b',
			color: '#fff',
			borderRadius: '4px',
			fontSize: '1rem',
			cursor: 'pointer',
			maxWidth: '150px',
		},
	};
	
	return (
		<>
			<h1>ATP Connect Settings</h1>
			<form style={styles.form} onSubmit={handleSubmit}>
				<label style={styles.label}>
					<p>Define Post Types that should trigger a post via ATP Connect (comma separated):</p>
					<input
						type="text"
						style={styles.input}
						value={postTypes}
						onChange={(e) => setPostTypes(e.target.value)}
					/>
				</label>
				<label style={styles.label}>
					Host:
					<input
						type="text"
						style={styles.input}
						value={host}
						onChange={(e) => setHost(e.target.value)}
					/>
				</label>
				<label style={styles.label}>
					Username:
					<input
						type="text"
						style={styles.input}
						value={username}
						onChange={(e) => setUsername(e.target.value)}
					/>
				</label>
				<label style={styles.label}>
					Password:
					<input
						style={styles.inputPass}
						type="password"
						
						value={password}
						onChange={(e) => setPassword(e.target.value)}
					/>
				</label>
				<button style={styles.button} type="submit">Save</button>
				{ saved && <label style={styles.labelSaved}>SAVED!</label> }
			</form>
		</>
    );
}

//Main component for admin page app
export default function App({ getSettings, updateSettings }) {
	//Track settings state
	const [settings, setSettings] = useState({});
	//Use to show loading spinner

	const [isLoading, setIsLoading] = useState(true);
	//When app loads, get settings
	useEffect(() => {
		getSettings().then((r) => {
			setSettings(r);
			setIsLoading(false);
		});
	}, [getSettings, setSettings]);

    //Function to update settings via API
	const onSave = () => {
		updateSettings(settings).then((r) => {
			setSettings(r);
		});
	};

	//Show a spinner if loading
	if (isLoading) {
		return <div className="spinner" style={{ visibility: "visible" }} />;
	}

	//Show settings if not loading
	return (
		<div>
			<LoginForm settings={settings} />
		</div>
	);
}
