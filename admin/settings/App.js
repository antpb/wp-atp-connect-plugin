import { useState, useEffect } from "@wordpress/element";
import { BskyAgent, AtpSessionEvent, AtpSessionData } from '@atproto/api';
// Initialize the agent with a persistSession function
const agent = new BskyAgent({
    service: 'https://axr.social/',
    persistSession: (evt, sess) => {
        // store the session-data for reuse
        // localStorage.setItem('atpSession', JSON.stringify(sess));
		fetch('/wp-json/your_namespace/v1/unified_session/', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': wpApiSettings.nonce, // Make sure to enqueue the wp-api script in your WordPress plugin or theme
			},
			body: JSON.stringify({ session_data: sess }),
		});		
    }
});

function SignUpForm() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [handle, setHandle] = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            console.log('Attempting sign up with email:', email);
            await agent.createAccount({
                email: email,
                password: password,
                handle: handle,
            });
            console.log('Account created successfully.');
        } catch (error) {
            console.error('Account creation error:', error);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <label>
                Email:
                <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                />
            </label>
            <label>
                Password:
                <input
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                />
            </label>
            <label>
                Handle:
                <input
                    type="text"
                    value={handle}
                    onChange={(e) => setHandle(e.target.value)}
                />
            </label>
            <button type="submit">Sign Up</button>
        </form>
    );
}

function Timeline() {
    const [post, setPost] = useState('');
    const [feedItems, setFeedItems] = useState([]);

    const handleTimeline = async (e) => {
        e.preventDefault();
        try {
            console.log('Attempting to create a post with text:', post);
            const response = await fetch('/wp-json/your_namespace/v1/unified_session/', {
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce,
                },
            });
            const sessionData = await response.json();
            if (!sessionData) {
                console.error('No session data found. Please log in first.');
                return;
            }
            await agent.resumeSession(sessionData);

            const res = await agent.getTimeline();
            console.log('Timeline here. Response:', res);
			//convert res.data.feed values to array
			const feed = Object.values(res.data.feed);
            setFeedItems(feed);
        } catch (error) {
            console.error('Post creation error:', error);
        }
    };

    return (
        <form onSubmit={handleTimeline}>
            <button type="submit">Get Timeline</button>
            {post && <p>{post}</p>}
            {Array.isArray(feedItems) && feedItems?.map((item, index) => (
                <p key={index}>{item?.post?.record?.text}</p>
            ))}
        </form>
    );
}

function PostForm() {
    const [post, setText] = useState('');

    const handleSubmit = async (e) => {
        // e.preventDefault();
        try {
            console.log('Attempting to create a post with text:', post);
            // const sessionData = JSON.parse(localStorage.getItem('atpSession'));
			const response = await fetch('/wp-json/your_namespace/v1/unified_session/', {
				headers: {
					'X-WP-Nonce': wpApiSettings.nonce, // Make sure to enqueue the wp-api script in your WordPress plugin or theme
				},
			});
			const sessionData = await response.json();			
            if (!sessionData) {
                console.error('No session data found. Please log in first.');
                return;
            }
			await agent.resumeSession(sessionData)

            const res = await agent.post({
				$type: 'app.bsky.feed.post',
                text: post,
                createdAt: (new Date()).toISOString(),
            });
            console.log('Post created successfully. Response:', res);
        } catch (error) {
            console.error('Post creation error:', error);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <label>
                Post:
                <input
                    type="text"
                    value={post}
                    onChange={(e) => setText(e.target.value)}
                />
            </label>
            <button type="submit">Create Post</button>
        </form>
    );
}


function LoginForm() {
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');

const handleSubmit = async (e) => {
    e.preventDefault();
    try {
        console.log('Attempting login with username:', username);
        const sessionData = await agent.login({ identifier: username, password });
        console.log('Login successful. Session data:', sessionData);
    } catch (error) {
        console.error('Login error:', error);
    }
};
    return (
        <form onSubmit={handleSubmit}>
            <label>
                Username:
                <input
                    type="text"
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                />
            </label>
            <label>
                Password:
                <input
					style={{ maxWidth: "50px" }}
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                />
            </label>
            <button type="submit">Login</button>
        </form>
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
			<LoginForm />
			<PostForm />
			<Timeline />
			<h2>SIGNUP FORM</h2>
			{/* <SignUpForm /> */}
			{/* <div>{settings.enabled ? "Enabled" : "Not enabled"}</div>
			<div>
				<label htmlFor="enabled">Enable</label>
				<input
					id="enabled"
					type="checkbox"
					name="enabled"
					value={settings.enabled}
					onChange={() => {
						setSettings({ ...settings, enabled: !settings.enabled });
					}}
				/>
			</div>
			<div>
				<label htmlFor="save">Save</label>
				<input id="save" type="submit" name="enabled" onClick={onSave} />
			</div> */}
		</div>
	);
}
