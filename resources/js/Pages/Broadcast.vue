<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';
import Peer from "simple-peer";
import { getPermissions } from "../utils";
import { ref, reactive } from 'vue';
const page = usePage();

const authUser = page.props.auth.user;
const videoStream = ref(null);
const userStream = ref(null);
const broadcasterId = ref(null);
const isVisibleLink = ref(false);
const streamingUsers = ref([]);
const allPeers = reactive({});
// const roomId = ref(Math.random().toString(36).substring(2,10));
const roomId = ref('9kzm6dto');

const startChat = async () => {
    // microphone and camera permissions
    const stream = await getPermissions();
    videoStream.value.srcObject = stream;

    initializeStreamingChannel();
    listenSignalAnswerChannel(); // a private channel where the broadcaster listens to incoming signalling answer
    // listenSignalOfferChannel();
    isVisibleLink.value = true;
};

const joinChat = async () => {
    // microphone and camera permissions
    const stream = await getPermissions();
    videoStream.value.srcObject = stream;

    joinStreamingChannel();
    listenSignalOfferChannel();
    listenSignalAnswerChannel(); // a private channel where the broadcaster listens to incoming signalling answer
    
};

const joinStreamingChannel = async (initiator = false) => {
Echo.join(`streaming-channel.${roomId.value}`)
    .here((users) => {
        console.log('all users', users);
        
        streamingUsers.value = users;
        // if this new user is not already on the call, send your stream offer
        const otherUsers = streamingUsers.value.filter(
            (user) => user.id !== authUser.id && !user.initiator
        );

        console.log('otherUsers', otherUsers);
        
        otherUsers.forEach(user => createPeer(user, true));
    })
    .joining((user) => {
        console.log("New User", user);
    })
    .leaving((user) => {
        console.log(user.name, "Left");
        // destroy peer
        allPeers[user.id].getPeer().destroy();
        // delete peer object
        delete allPeers[user.id];
        // if one leaving is the broadcaster set streamingUsers to empty array
        if (user.id === authUser.id) {
            streamingUsers.value = [];
        } else {
            // remove from streamingUsers array
            const leavingUserIndex = streamingUsers.findIndex(
                (data) => data.id === user.id
            );
            streamingUsers.value.splice(leavingUserIndex, 1);
        }
    });
};

const initializeStreamingChannel = async () => {
Echo.join(`streaming-channel.${roomId.value}`)
    .here((users) => {
        console.log('all users', users);
        
        streamingUsers.value = users;
    })
    .joining((user) => {
        console.log("New User Joining", user);
        // // if this new user is not already on the call, send your stream offer
        const joiningUserIndex = streamingUsers.value.findIndex(
            (data) => data.id === user.id
        );
        if (joiningUserIndex < 0) {
            createPeer(user, true);
            streamingUsers.value.push(user);
        }
    })
    .leaving((user) => {
        console.log(user.name, "Left");
        // destroy peer
        allPeers[user.id].getPeer().destroy();
        // delete peer object
        delete allPeers[user.id];
        // if one leaving is the broadcaster set streamingUsers to empty array
        if (user.id === authUser.id) {
            streamingUsers.value = [];
        } else {
            // remove from streamingUsers array
            const leavingUserIndex = streamingUsers.findIndex(
                (data) => data.id === user.id
            );
            streamingUsers.value.splice(leavingUserIndex, 1);
        }
    });
};
const createPeer = (user, initiator = false) => {
    console.log('createPeer', user);
    
    // A new user just joined the channel so signal that user
    allPeers[user.id] = peerCreator(
        videoStream.value.srcObject,
        user,
        initiator
    )
    // Create Peer
    allPeers[user.id].create();
    // Initialize Events
    allPeers[user.id].initEvents();
}
const listenSignalAnswerChannel = () => {
Echo.private(`stream-signal-channel.${authUser.id}`)
    .listen("StreamAnswer", ({ data }) => {
        console.log("Signal Answer from private channel", data.user);
        if (data.answer.renegotiate) {
            console.log("renegotating");
        }
        if (data.answer.sdp) {
            const updatedSignal = {
                ...data.answer,
                sdp: `${data.answer.sdp}\n`,
            };
            console.log(authUser.id);
            
            allPeers[data.user]
                .getPeer()
                .signal(updatedSignal);
            }
        }
    );
};
const offerChat = (offer, user) => {
    console.log('offerChat', user);
    
    axios
        .post("/stream-offer", {
            broadcaster: authUser.id,
            receiver: user,
            offer,
        })
        .then((res) => {
            console.log('here', res);
        })
        .catch((err) => {
            console.log(err);
        });
};

const peerCreator = (stream, user, initiator = false) => {
    let peer;
    return {
        create: () => {
            peer = new Peer({
                initiator: initiator,
                trickle: false,
                stream: stream,
                config: {
                    iceServers: [
                        {
                            urls: ["stun:stun.stunprotocol.org", "stun:stun1.l.google.com:19302"],
                        },
                        // {
                        //     urls: [process.env.TURN_SERVER_HOST],
                        //     username: process.env.TURN_SERVER_USER,
                        //     credential: process.env.TURN_SERVER_CRED,
                        // },
                    ],
                },
            });
        },
        getPeer: () => peer,
        initEvents: (incomingOffer = null) => {
            peer.on("signal", (offer) => {
                // send or accept offer over here.
                incomingOffer ? answerChat(offer, user) : offerChat(offer, user);
            });
            peer.on("stream", (stream) => {
                console.log("onStream", user);
                console.log(allPeers);
                
                // userStream.value.srcObject = stream;
                allPeers[incomingOffer ? user : user.id].stream = stream;
            });
            peer.on("track", (track, stream) => {
                console.log("onTrack");
            });
            peer.on("connect", () => {
                console.log("Broadcaster Peer connected");
            });
            peer.on("close", (data) => {
                console.log("Broadcaster Peer closed", data);
                removeBroadcastVideo();
            });
            peer.on("error", (err) => {
                console.log(err);
                
                console.log("handle error gracefully");
            });

            if(incomingOffer) {
                const updatedOffer = {
                    ...incomingOffer,
                    sdp: `${incomingOffer.sdp}\n`,
                };
                peer.signal(updatedOffer);
            }
        },

    };
}
const listenSignalOfferChannel = () => {
    Echo.private(`stream-signal-channel.${authUser.id}`)
        .listen("StreamOffer", ({ data }) => {
            console.log("Signal Offer from private channel", data.broadcaster);
            broadcasterId.value = data.broadcaster;
            allPeers[data.broadcaster] = peerCreator(
                videoStream.value.srcObject,
                data.broadcaster
            )
            // Create Peer
            allPeers[data.broadcaster].create();
            // Initialize Events
            allPeers[data.broadcaster].initEvents(data.offer);
        });
};
const answerChat =(offer, broadcaster) => {
    console.log('answerChat', broadcaster);
    axios
        .post("/stream-answer", {
            broadcaster,
            answer: offer,
        })
        .then((res) => {
            console.log(res);
        })
        .catch((err) => {
            console.log(err);
        });
};
const removeBroadcastVideo = () => {
    console.log("removingBroadcast Video");
    alert("Livestream ended by broadcaster");
    const tracks = videoStream.value.srcObject.getTracks();
    tracks.forEach((track) => {
        track.stop();
    });
    videoStream.value.srcObject = null;
};
</script>

<template>
    <AppLayout title="Stream">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Stream
            </h2>
        </template>

        <div class="py-12">
            <button @click="startChat" class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Start
            </button>
            <button @click="joinChat" class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Join
            </button>
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg flex flex-wrap">
                    <div class="stream-windwo">
                        <video autoplay muted ref="videoStream"></video>
                    </div>
                    <!-- <video autoplay ref="userStream"></video> -->
                     <div class="stream-window" v-for="(peer, index) in Object.values(allPeers)" :key="index">
                         <video autoplay :srcObject="peer.stream"></video>
                     </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
